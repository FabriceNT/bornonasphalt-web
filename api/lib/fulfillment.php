<?php
// Creates a draft order on BOTH Printful and Printify, compares their total
// cost (product + shipping) for this specific cart and address, confirms /
// sends to production whichever is cheaper, and cancels the other.
//
// ⚠️  DRY-RUN MODE ACTIF (BOA_FULFILLMENT_DRY_RUN = true)
// En mode dry-run, les deux drafts sont créés et comparés, mais AUCUN
// n'est confirmé ni envoyé en production — les deux restent en brouillon.
// Les coûts réels sont loggués dans orders.log pour vérification.
// Passer à false uniquement quand tu es prêt à facturer des vraies commandes.
//
const BOA_FULFILLMENT_DRY_RUN = false;

// Returns: [
//   'chosen_provider' => 'printful' | 'printify' | 'dry-run',
//   'chosen_order_id' => ...,
//   'printful_total_cents' => int|null,
//   'printify_total_cents' => int|null,
// ]
//
// Throws only if BOTH providers fail — a single provider failing just means
// we go with the other one and log the failure.
function boa_fulfill_cheapest(array $cartItems, array $shipping): array
{
    $printfulResult = null;
    $printfulError  = null;
    $printifyResult = null;
    $printifyError  = null;

    try {
        $printfulResult = boa_printful_create_draft_order($cartItems, $shipping);
    } catch (Exception $e) {
        $printfulError = $e->getMessage();
        error_log('Printful draft order failed: ' . $printfulError);
    }

    try {
        $printifyResult = boa_printify_create_draft_order($cartItems, $shipping);
    } catch (Exception $e) {
        $printifyError = $e->getMessage();
        error_log('Printify draft order failed: ' . $printifyError);
    }

    if ($printfulResult === null && $printifyResult === null) {
        throw new Exception(
            "Both providers failed. Printful: {$printfulError} | Printify: {$printifyError}"
        );
    }

    $printfulTotal = $printfulResult['total_cents'] ?? null;
    $printifyTotal = $printifyResult['total_cents'] ?? null;

    // Déterminer le gagnant théorique pour le log
    if ($printfulTotal > 0 && ($printifyTotal <= 0 || $printfulTotal <= $printifyTotal)) {
        $winner = 'printful';
    } elseif ($printifyTotal > 0) {
        $winner = 'printify';
    } else {
        $winner = $printfulResult ? 'printful' : 'printify';
    }

    // ----------------------------------------------------------------
    // DRY-RUN : on logue mais on ne confirme/envoie rien en production.
    // Les deux drafts restent en brouillon — annule-les manuellement
    // sur les dashboards Printful/Printify après vérification.
    // ----------------------------------------------------------------
    if (BOA_FULFILLMENT_DRY_RUN) {
        $pfId  = $printfulResult['id']  ?? 'n/a';
        $pyId  = $printifyResult['id']  ?? 'n/a';
        $pfCts = $printfulTotal          ?? 'n/a';
        $pyCts = $printifyTotal          ?? 'n/a';

        error_log(sprintf(
            '[DRY-RUN] winner=%s | printful=%s¢ (draft %s) | printify=%s¢ (draft %s) — aucun envoyé en production',
            $winner, $pfCts, $pfId, $pyCts, $pyId
        ));

        return [
            'chosen_provider'      => 'dry-run',
            'chosen_order_id'      => null,
            'printful_draft_id'    => $pfId,
            'printify_draft_id'    => $pyId,
            'printful_total_cents' => $printfulTotal,
            'printify_total_cents' => $printifyTotal,
            'would_have_chosen'    => $winner,
            'note'                 => 'DRY-RUN — aucune commande envoyée en production. '
                                    . 'Passe BOA_FULFILLMENT_DRY_RUN à false pour activer la production.',
        ];
    }

    // ----------------------------------------------------------------
    // MODE PRODUCTION — actif uniquement quand DRY_RUN = false
    // ----------------------------------------------------------------

    // Un seul provider a réussi
    if ($printfulResult === null) {
        boa_printify_send_to_production($printifyResult['id']);
        return [
            'chosen_provider'      => 'printify',
            'chosen_order_id'      => $printifyResult['id'],
            'printful_total_cents' => null,
            'printify_total_cents' => $printifyTotal,
            'note'                 => "Printful failed ({$printfulError}), used Printify by default.",
        ];
    }
    if ($printifyResult === null) {
        boa_printful_confirm_order($printfulResult['id']);
        return [
            'chosen_provider'      => 'printful',
            'chosen_order_id'      => $printfulResult['id'],
            'printful_total_cents' => $printfulTotal,
            'printify_total_cents' => null,
            'note'                 => "Printify failed ({$printifyError}), used Printful by default.",
        ];
    }

    // Les deux ont réussi — on prend le moins cher
    // Si l'un retourne 0, il n'a pas pu calculer ses coûts : on préfère
    // l'autre plutôt que de faire confiance à un 0.
    if ($printfulTotal > 0 && ($printifyTotal <= 0 || $printfulTotal <= $printifyTotal)) {
        boa_printful_confirm_order($printfulResult['id']);
        boa_printify_cancel_order($printifyResult['id']);
        return [
            'chosen_provider'      => 'printful',
            'chosen_order_id'      => $printfulResult['id'],
            'printful_total_cents' => $printfulTotal,
            'printify_total_cents' => $printifyTotal,
        ];
    }

    boa_printify_send_to_production($printifyResult['id']);
    boa_printful_cancel_order($printfulResult['id']);
    return [
        'chosen_provider'      => 'printify',
        'chosen_order_id'      => $printifyResult['id'],
        'printful_total_cents' => $printfulTotal,
        'printify_total_cents' => $printifyTotal,
    ];
}
