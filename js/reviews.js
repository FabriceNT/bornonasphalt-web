// reviews.js — Born on Asphalt

// ---- Utilitaires ----

function boaStars(rating, interactive = false, name = '') {
  return '<span class="boa-stars">' + [1,2,3,4,5].map(n => {
    if (interactive) return `<label class="boa-star-label"><input type="radio" name="${name}" value="${n}" required><span class="boa-star" data-val="${n}">★</span></label>`;
    return `<span class="boa-star ${n <= Math.round(rating) ? 'filled' : 'empty'}">★</span>`;
  }).join('') + '</span>';
}

function boaTimeAgo(dateStr) {
  const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
  if (diff < 86400)    return 'today';
  if (diff < 2592000)  return Math.floor(diff / 86400) + ' days ago';
  if (diff < 31536000) return Math.floor(diff / 2592000) + ' months ago';
  return Math.floor(diff / 31536000) + ' years ago';
}

// ---- Lightbox ----

function boaOpenLightbox(photos, startIndex) {
  let current = startIndex;
  const overlay = document.createElement('div');
  overlay.className = 'boa-lightbox-overlay';

  const render = () => {
    overlay.innerHTML = `
      <div class="boa-lightbox">
        <button class="boa-lightbox-close" aria-label="Close">✕</button>
        ${photos.length > 1 ? `<button class="boa-lightbox-prev" aria-label="Previous">‹</button>` : ''}
        <img class="boa-lightbox-img" src="${photos[current]}" alt="Review photo ${current + 1}" />
        ${photos.length > 1 ? `<button class="boa-lightbox-next" aria-label="Next">›</button>` : ''}
        ${photos.length > 1 ? `<div class="boa-lightbox-counter">${current + 1} / ${photos.length}</div>` : ''}
      </div>`;
    overlay.querySelector('.boa-lightbox-close').onclick = () => overlay.remove();
    overlay.querySelector('.boa-lightbox-prev')?.addEventListener('click', () => { current = (current - 1 + photos.length) % photos.length; render(); });
    overlay.querySelector('.boa-lightbox-next')?.addEventListener('click', () => { current = (current + 1) % photos.length; render(); });
  };

  render();
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') overlay.remove(); }, { once: true });
  document.body.appendChild(overlay);
}

// ---- Galerie photos dans une review ----

function boaReviewPhotos(photos) {
  if (!photos || photos.length === 0) return '';
  const thumbs = photos.map((p, i) =>
    `<img class="boa-review-thumb" src="${p}" alt="Photo ${i+1}" data-index="${i}" loading="lazy" />`
  ).join('');
  return `<div class="boa-review-photos">${thumbs}</div>`;
}

// ---- Résumé produit ----

function boaRenderProductSummary(summary, container) {
  if (!summary || summary.total === 0) {
    container.innerHTML = `<div class="boa-reviews-empty">${boaStars(0)}<span class="boa-reviews-count">No reviews yet — be the first.</span></div>`;
    return;
  }
  const { average, total, breakdown } = summary;
  const bars = [5,4,3,2,1].map(n => {
    const pct = total > 0 ? Math.round((breakdown[n] / total) * 100) : 0;
    return `<div class="boa-rating-bar-row">
      <span class="boa-rating-bar-label">${n}★</span>
      <div class="boa-rating-bar-track"><div class="boa-rating-bar-fill" style="width:${pct}%"></div></div>
      <span class="boa-rating-bar-count">${breakdown[n]}</span>
    </div>`;
  }).join('');
  container.innerHTML = `
    <div class="boa-reviews-summary">
      <div class="boa-reviews-summary-avg">
        <span class="boa-avg-score">${average.toFixed(1)}</span>
        ${boaStars(average)}
        <span class="boa-reviews-count">${total} review${total > 1 ? 's' : ''}</span>
      </div>
      <div class="boa-rating-bars">${bars}</div>
    </div>`;
}

// ---- Liste de reviews ----

function boaRenderReviews(reviews, container) {
  if (!reviews || reviews.length === 0) {
    container.innerHTML = '<p class="boa-reviews-empty-list">No reviews yet.</p>';
    return;
  }
  container.innerHTML = reviews.map(r => `
    <div class="boa-review-card">
      <div class="boa-review-header">
        ${boaStars(r.rating)}
        ${r.verified ? '<span class="boa-verified-badge">✓ Verified Purchase</span>' : ''}
      </div>
      ${r.title ? `<div class="boa-review-title">${r.title}</div>` : ''}
      <p class="boa-review-body">${r.body}</p>
      ${boaReviewPhotos(r.photos)}
      <div class="boa-review-meta">
        <span class="boa-review-author">${r.display_name}</span>
        ${r.color ? `<span class="boa-review-color">${r.color}${r.size ? ' · ' + r.size : ''}</span>` : ''}
        <span class="boa-review-date">${boaTimeAgo(r.created_at)}</span>
      </div>
    </div>`).join('');

  // Clic sur une miniature → lightbox
  container.querySelectorAll('.boa-review-card').forEach(card => {
    const thumbs = card.querySelectorAll('.boa-review-thumb');
    if (!thumbs.length) return;
    const photos = Array.from(thumbs).map(t => t.src);
    thumbs.forEach((t, i) => t.addEventListener('click', () => boaOpenLightbox(photos, i)));
  });
}

// ---- Formulaire avec upload photos ----

function boaRenderMyReview(review, productId, container) {
  const stars = boaStars(review.rating);
  container.innerHTML = `
    <div class="boa-review-form-wrap" id="boaMyReviewWrap">
      <h3 class="boa-review-form-title">Your Review</h3>
      <div class="boa-review-card" style="margin-bottom:16px;">
        <div class="boa-review-header">${stars}</div>
        ${review.title ? `<div class="boa-review-title">${review.title}</div>` : ''}
        <p class="boa-review-body">${review.body}</p>
      </div>
      <button class="boa-review-submit-btn" id="boaEditReviewBtn">Edit My Review</button>
      <div id="boaEditReviewFormArea"></div>
    </div>`;

  document.getElementById('boaEditReviewBtn').addEventListener('click', () => {
    const area = document.getElementById('boaEditReviewFormArea');
    area.innerHTML = `
      <div style="margin-top:20px;">
        <div class="boa-form-row">
          <label>Your rating *</label>
          <div class="boa-stars-input" id="boaEditStars">${boaStars(review.rating, true, 'edit_rating')}</div>
        </div>
        <div class="boa-form-row">
          <label for="boa-edit-title">Review title</label>
          <input type="text" id="boa-edit-title" value="${review.title || ''}" maxlength="120" />
        </div>
        <div class="boa-form-row">
          <label for="boa-edit-body">Your review *</label>
          <textarea id="boa-edit-body" rows="4" minlength="10">${review.body}</textarea>
        </div>
        <div class="boa-form-actions">
          <button type="button" class="boa-review-submit-btn" id="boaSaveReviewBtn">Save Changes</button>
        </div>
        <div class="boa-form-row">
          <label>Photos <span class="boa-form-hint">(optional — max 3, JPEG/PNG/WebP, 8 MB each)</span></label>
          <div class="boa-photo-upload-area" id="boaEditPhotoArea">
            <label class="boa-photo-add-btn" for="boaEditPhotoInput">+ Add Photo</label>
            <input type="file" id="boaEditPhotoInput" accept="image/jpeg,image/png,image/webp" multiple hidden />
            <div class="boa-photo-previews" id="boaEditPhotoPreviews"></div>
          </div>
          <div class="boa-upload-status" id="boaEditUploadStatus"></div>
        </div>
        <div id="boa-edit-feedback" class="boa-review-feedback" hidden></div>
      </div>`;

    // Étoiles interactives
    const starsWrap = document.getElementById('boaEditStars');
    let selectedRating = review.rating;
    starsWrap.querySelectorAll('.boa-star').forEach(el => {
      if (+el.dataset.val <= selectedRating) el.classList.add('selected');
    });
    starsWrap.addEventListener('mouseover', e => {
      const s = e.target.closest('.boa-star'); if (!s) return;
      const val = +s.dataset.val;
      starsWrap.querySelectorAll('.boa-star').forEach(el =>
        el.classList.toggle('hover', +el.dataset.val <= val));
    });
    starsWrap.addEventListener('mouseleave', () =>
      starsWrap.querySelectorAll('.boa-star').forEach(el => el.classList.remove('hover')));
    starsWrap.addEventListener('change', () => {
      const checked = starsWrap.querySelector('input[name="edit_rating"]:checked');
      selectedRating = checked ? +checked.value : selectedRating;
      starsWrap.querySelectorAll('.boa-star').forEach(el =>
        el.classList.toggle('selected', +el.dataset.val <= selectedRating));
    });

    // Upload photos — pré-charger les photos existantes
    const uploadedPaths = review.photos ? [...review.photos] : [];
    const previewsEl   = document.getElementById('boaEditPhotoPreviews');
    const statusEl     = document.getElementById('boaEditUploadStatus');
    const fileInput    = document.getElementById('boaEditPhotoInput');
    const addBtn       = document.querySelector('label[for="boaEditPhotoInput"]');

    const refreshAddBtn = () => {
      addBtn.style.display = uploadedPaths.length >= 3 ? 'none' : 'inline-flex';
    };

    // Afficher les miniatures des photos existantes
    uploadedPaths.forEach((path, idx) => {
      const wrap = document.createElement('div');
      wrap.className = 'boa-photo-preview-wrap';
      wrap.innerHTML = `<img src="${path}" class="boa-photo-preview-thumb" />
        <button class="boa-photo-remove-btn" data-idx="${idx}">✕</button>`;
      wrap.querySelector('.boa-photo-remove-btn').addEventListener('click', () => {
        uploadedPaths.splice(idx, 1);
        wrap.remove();
        refreshAddBtn();
      });
      previewsEl.appendChild(wrap);
    });
    refreshAddBtn();

    fileInput.addEventListener('change', async () => {
      const files = Array.from(fileInput.files);
      fileInput.value = '';
      for (const file of files) {
        if (uploadedPaths.length >= 3) break;
        statusEl.textContent = 'Uploading…';
        const fd = new FormData();
        fd.append('photo', file);
        try {
          const r = await fetch('/api/reviews-upload.php', {
            method: 'POST', credentials: 'include', body: fd,
          });
          const d = await r.json();
          if (d.path) {
            uploadedPaths.push(d.path);
            const wrap = document.createElement('div');
            wrap.className = 'boa-photo-preview-wrap';
            const currentIdx = uploadedPaths.length - 1;
            wrap.innerHTML = `<img src="${d.path}" class="boa-photo-preview-thumb" />
              <button class="boa-photo-remove-btn">✕</button>`;
            wrap.querySelector('.boa-photo-remove-btn').addEventListener('click', () => {
              const i = uploadedPaths.indexOf(d.path);
              if (i > -1) uploadedPaths.splice(i, 1);
              wrap.remove();
              refreshAddBtn();
            });
            previewsEl.appendChild(wrap);
            statusEl.textContent = '';
          } else {
            statusEl.textContent = d.error || 'Upload failed.';
          }
        } catch {
          statusEl.textContent = 'Upload error.';
        }
        refreshAddBtn();
      }
    });

    document.getElementById('boaSaveReviewBtn').addEventListener('click', async () => {
      const body = document.getElementById('boa-edit-body').value.trim();
      const feedback = document.getElementById('boa-edit-feedback');
      if (body.length < 10) {
        showFeedback(feedback, 'Review must be at least 10 characters.', 'error'); return;
      }
      const btn = document.getElementById('boaSaveReviewBtn');
      btn.disabled = true; btn.textContent = 'Saving…';
      try {
        const res = await fetch('/api/reviews-update.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
          body: JSON.stringify({
            review_id: review.id,
            rating:    selectedRating,
            title:     document.getElementById('boa-edit-title').value.trim(),
            body,
            photos:    [...uploadedPaths],   // NOUVEAU
          }),
        });
        const data = await res.json();
        if (data.success) {
          document.getElementById('boaMyReviewWrap').innerHTML = `
            <div class="boa-review-thanks">
              <div class="boa-review-thanks-icon">✓</div>
              <p>${data.message}</p>
            </div>`;
        } else {
          showFeedback(feedback, data.error || 'Could not update review.', 'error');
          btn.disabled = false; btn.textContent = 'Save Changes';
        }
      } catch {
        showFeedback(feedback, 'Network error. Please try again.', 'error');
        btn.disabled = false; btn.textContent = 'Save Changes';
      }
    });
  });
}

function boaRenderReviewForm(productId, color, size, formContainer) {
  formContainer.innerHTML = `
    <div class="boa-review-form-wrap">
      <h3 class="boa-review-form-title">Leave a Review</h3>
      <form class="boa-review-form" id="boaReviewForm" novalidate>
        <div class="boa-form-row">
          <label>Your rating *</label>
          <div class="boa-stars-input">${boaStars(0, true, 'rating')}</div>
        </div>
        <div class="boa-form-row" id="boa-rev-email-row">
          <label for="boa-rev-email">Email * <span class="boa-form-hint">(verifies your purchase — never displayed)</span></label>
          <input type="email" id="boa-rev-email" placeholder="you@example.com" required />
        </div>
        <div class="boa-form-row" id="boa-rev-name-row">
          <label for="boa-rev-name">Display name</label>
          <input type="text" id="boa-rev-name" placeholder="Mike T." maxlength="80" />
        </div>
        <div class="boa-form-row">
          <label for="boa-rev-title">Review title</label>
          <input type="text" id="boa-rev-title" placeholder="Exactly what I wanted" maxlength="120" />
        </div>
        <div class="boa-form-row">
          <label for="boa-rev-body">Your review *</label>
          <textarea id="boa-rev-body" rows="4" placeholder="Tell us about the quality, fit, print..." required minlength="10"></textarea>
        </div>
        <div class="boa-form-row">
          <label>Photos <span class="boa-form-hint">(optional — max 3, JPEG/PNG/WebP, 8 MB each)</span></label>
          <div class="boa-photo-upload-area" id="boaPhotoArea">
            <label class="boa-photo-add-btn" for="boaPhotoInput">+ Add Photo</label>
            <input type="file" id="boaPhotoInput" accept="image/jpeg,image/png,image/webp" multiple hidden />
            <div class="boa-photo-previews" id="boaPhotoPreviews"></div>
          </div>
          <div class="boa-upload-status" id="boaUploadStatus"></div>
        </div>
        <div class="boa-form-actions">
          <button type="submit" class="boa-review-submit-btn">Submit Review</button>
        </div>
        <div id="boa-review-feedback" class="boa-review-feedback" hidden></div>
      </form>
    </div>`;

  // Étoiles interactives
  const starsWrap = formContainer.querySelector('.boa-stars-input');
  starsWrap.addEventListener('mouseover', e => {
    const s = e.target.closest('.boa-star'); if (!s) return;
    const val = +s.dataset.val;
    starsWrap.querySelectorAll('.boa-star').forEach(el => el.classList.toggle('hover', +el.dataset.val <= val));
  });
  starsWrap.addEventListener('mouseleave', () =>
    starsWrap.querySelectorAll('.boa-star').forEach(el => el.classList.remove('hover'))
  );
  starsWrap.addEventListener('change', () => {
    const val = +starsWrap.querySelector('input[name="rating"]:checked')?.value || 0;
    starsWrap.querySelectorAll('.boa-star').forEach(el => el.classList.toggle('selected', +el.dataset.val <= val));
  });

  // Upload photos
  const uploadedPaths = [];
  const previewsEl   = formContainer.querySelector('#boaPhotoPreviews');
  const statusEl     = formContainer.querySelector('#boaUploadStatus');
  const fileInput    = formContainer.querySelector('#boaPhotoInput');
  const addBtn       = formContainer.querySelector('.boa-photo-add-btn');

  const refreshAddBtn = () => {
    addBtn.style.display = uploadedPaths.length >= 3 ? 'none' : '';
  };

  fileInput.addEventListener('change', async () => {
    const files = Array.from(fileInput.files).slice(0, 3 - uploadedPaths.length);
    if (!files.length) return;
    fileInput.value = '';

    for (const file of files) {
      if (uploadedPaths.length >= 3) break;
      statusEl.textContent = `Uploading ${file.name}…`;
      const fd = new FormData();
      fd.append('photo', file);
      try {
        const res  = await fetch('/api/reviews-upload.php', { method: 'POST', credentials: 'include', body: fd });
        const data = await res.json();
        if (data.path) {
          uploadedPaths.push(data.path);
          const wrap = document.createElement('div');
          wrap.className = 'boa-photo-preview';
          const idx = uploadedPaths.length - 1;
          wrap.innerHTML = `<img src="${data.path}" alt="Preview" /><button type="button" class="boa-photo-remove" data-idx="${idx}">✕</button>`;
          wrap.querySelector('.boa-photo-remove').addEventListener('click', () => {
            uploadedPaths.splice(idx, 1);
            wrap.remove();
            // Re-index remaining remove buttons
            previewsEl.querySelectorAll('.boa-photo-remove').forEach((b, i) => b.dataset.idx = i);
            refreshAddBtn();
          });
          previewsEl.appendChild(wrap);
          statusEl.textContent = '';
        } else {
          statusEl.textContent = data.error || 'Upload failed.';
        }
      } catch {
        statusEl.textContent = 'Upload failed. Check your connection.';
      }
      refreshAddBtn();
    }
    statusEl.textContent = uploadedPaths.length > 0 ? `${uploadedPaths.length} photo${uploadedPaths.length > 1 ? 's' : ''} ready.` : '';
  });

  // Soumission
  formContainer.querySelector('#boaReviewForm').addEventListener('submit', async e => {
    e.preventDefault();
    const rating = +formContainer.querySelector('input[name="rating"]:checked')?.value || 0;
    const email  = formContainer.querySelector('#boa-rev-email').value.trim();
    const body   = formContainer.querySelector('#boa-rev-body').value.trim();
    const feedback = formContainer.querySelector('#boa-review-feedback');

    if (!rating) { showFeedback(feedback, 'Please select a star rating.', 'error'); return; }
    if (!email)  { showFeedback(feedback, 'Email is required.', 'error'); return; }
    if (body.length < 10) { showFeedback(feedback, 'Review must be at least 10 characters.', 'error'); return; }

    const btn = formContainer.querySelector('.boa-review-submit-btn');
    btn.disabled = true; btn.textContent = 'Submitting…';

    try {
      const res = await fetch('/api/reviews-submit.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include',
        body: JSON.stringify({
          product_id:   productId,
          rating,
          title:        formContainer.querySelector('#boa-rev-title').value.trim(),
          body,
          email,
          display_name: formContainer.querySelector('#boa-rev-name').value.trim(),
          color, size,
          photos:       [...uploadedPaths],
        }),
      });
      const data = await res.json();
      if (data.success) {
        formContainer.querySelector('.boa-review-form-wrap').innerHTML = `
          <div class="boa-review-thanks">
            <div class="boa-review-thanks-icon">✓</div>
            <p>${data.message}</p>
            ${data.verified ? '<p class="boa-verified-note">Your purchase was verified.</p>' : ''}
          </div>`;
      } else {
        showFeedback(feedback, data.error || 'Could not submit review.', 'error');
        btn.disabled = false; btn.textContent = 'Submit Review';
      }
    } catch {
      showFeedback(feedback, 'Network error. Please try again.', 'error');
      btn.disabled = false; btn.textContent = 'Submit Review';
    }
  });

}

function showFeedback(el, msg, type) {
  el.textContent = msg;
  el.className = `boa-review-feedback ${type}`;
  el.hidden = false;
}

// ---- Chargement reviews produit ----

async function boaLoadProductReviews(productId, color, size) {
  const summaryEl = document.getElementById('boa-product-review-summary');
  const listEl    = document.getElementById('boa-product-review-list');
  const formEl    = document.getElementById('boa-product-review-form');
  if (!summaryEl) return;

  let data = null;
  try {
    const res  = await fetch(`/api/reviews-get.php?product_id=${encodeURIComponent(productId)}`);
    data = await res.json();
    boaRenderProductSummary(data.summary, summaryEl);
    if (listEl) boaRenderReviews(data.reviews, listEl);
  } catch { /* silencieux */ }

  if (formEl) {
    const user = window._boaUserPromise ? await window._boaUserPromise : null;
    if (data && data.my_review) {
      boaRenderMyReview(data.my_review, productId, formEl);
    } else {
      boaRenderReviewForm(productId, color || '', size || '', formEl);
      if (user) boaUpdateReviewFormUser(user);
    }
  }
}

// ---- Chargement reviews home ----

async function boaLoadFeaturedReviews() {
  const container = document.getElementById('boa-featured-reviews');
  const scoreEl   = document.getElementById('boa-global-score');
  if (!container) return;

  try {
    const [featRes, sumRes] = await Promise.all([
      fetch('/api/reviews-get.php?featured=1&limit=4'),
      fetch('/api/reviews-get.php?summary=1'),
    ]);
    const feat = await featRes.json();
    const sum  = await sumRes.json();

    if (scoreEl && sum.total > 0) {
      scoreEl.innerHTML = `${boaStars(sum.average)}
        <span class="boa-global-avg">${sum.average.toFixed(1)}</span>
        <span class="boa-global-count">${sum.total} verified review${sum.total > 1 ? 's' : ''}</span>`;
    }

    if (!feat.reviews || feat.reviews.length === 0) {
      container.closest('.boa-reviews-section')?.remove();
      return;
    }

    container.innerHTML = feat.reviews.map(r => `
      <div class="boa-featured-review-card">
        <div class="boa-review-header">
          ${boaStars(r.rating)}
          ${r.verified ? '<span class="boa-verified-badge">✓ Verified</span>' : ''}
        </div>
        ${r.title ? `<div class="boa-review-title">"${r.title}"</div>` : ''}
        <p class="boa-review-body">${r.body}</p>
        ${boaReviewPhotos(r.photos)}
        <div class="boa-review-meta">
          <span class="boa-review-author">${r.display_name}</span>
          ${r.color ? `<span class="boa-review-color">${r.color}${r.size ? ' · ' + r.size : ''}</span>` : ''}
        </div>
      </div>`).join('');

    // Lightbox sur les photos featured
    container.querySelectorAll('.boa-featured-review-card').forEach(card => {
      const thumbs = card.querySelectorAll('.boa-review-thumb');
      if (!thumbs.length) return;
      const photos = Array.from(thumbs).map(t => t.src);
      thumbs.forEach((t, i) => t.addEventListener('click', () => boaOpenLightbox(photos, i)));
    });
  } catch { /* silencieux */ }
}

function boaUpdateReviewFormUser(user) {
  if (!user) return;
  const emailInput = document.getElementById('boa-rev-email');
  const nameInput  = document.getElementById('boa-rev-name');
  const emailRow   = document.getElementById('boa-rev-email-row');
  const nameRow    = document.getElementById('boa-rev-name-row');
  if (emailInput && user.email) {
    emailInput.value = user.email;
    if (emailRow) emailRow.style.display = 'none';
  }
  if (nameInput && user.name) {
    nameInput.value = user.name;
    if (nameRow) nameRow.style.display = 'none';
  }
}
