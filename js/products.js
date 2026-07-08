// Colors/sizes here mirror what's actually synced on Printful (see
// api/products.php on the backend for the authoritative sync_variant_ids).
// Curated to on-brand dark tones per the style guide (max 3 colors/design).
// Prices depend on SIZE, not on the design — same schedule across the
// whole catalog. Shipping is a flat fee, waived above the free-shipping
// threshold. Kept here so both card "from" price and the size buttons can
// reference the same numbers.
const SIZE_PRICES = { 'S': 34.99, 'M': 34.99, 'L': 34.99, 'XL': 34.99, '2XL': 36.99, '3XL': 39.99, '4XL': 40.99 };
const SHIPPING_FLAT = 4.75;
const FREE_SHIPPING_THRESHOLD = 85;
const BASE_PRICE = 34.99; // shown on catalog cards as "From $X" before a size is picked

// Approximate hex values for rendering color swatches (Comfort Colors names).
const COLOR_HEX = {
  "Black": "#1b1b1b",
  "True Navy": "#233246",
  "Midnight": "#1c2733",
  "Mystic Blue": "#3d5a73",
  "Terracotta": "#b1543a",
  "Burnt orange": "#c4622d",
  "Granite": "#57585a",
  "Denim": "#2f4a63",
  "Graphite": "#4b4b4d",
  "Grey": "#7a7a78",
  "Red": "#a5333b",
  "Navy": "#26364a",
  "Hemp": "#a89f7e",
  "Pepper": "#4a423c",
  "Grape": "#5b3a5c",
  "Blue Spruce": "#3c5a52",
  "Crimson": "#7a2331",
  "Flo Blue": "#3f6e8c",
  "Violet": "#6a5a8c",
  "Yam": "#c1673b",
  "Seafoam": "#8fae9e",
  "Light Green": "#9dc08b",
  "Blue Jean": "#5b7fa6",
  "Crunchberry": "#8a3a4a",
  "Chalky Mint": "#a9cdb8",
  "White": "#efece2",
  "Paprika": "#a8492f",
  "Washed Denim": "#6683a0",
  "Bay": "#3f6b6a",
  "Sage": "#8a9a7b",
  "Moss": "#5f6f45",
  "Brick": "#8a4030",
  "Berry": "#6b2c46",
  "Espresso": "#3b2a22",
  "Island Reef": "#7fb0a3"
};

const PRODUCTS = [
  {
    id: "A1", series: "A — Storytelling Era", tribe: "cross", tribeLabel: "Cross-Tribe",
    title: "1969", slogan: "1969. Detroit at its most dangerous.", sub: "Before the insurance killed the dream.",
    desc: "Bold stencil year print with distressed texture. Comfort Colors 1717, garment-dyed heavyweight.",
    price: 34.99, swatch: "1969", image: "https:\/\/files.cdn.printful.com\/files\/0ca\/0caf913fc16cb8949c4c513a7b872567_preview.png", images: {"Black":"https://files.cdn.printful.com/files/0ca/0caf913fc16cb8949c4c513a7b872567_preview.png","True Navy":"https://files.cdn.printful.com/files/ea7/ea7771b7a10ea9cee38f28a16738c420_preview.png","Red":"https://files.cdn.printful.com/files/cf5/cf51b3c86f4356859a43800034352807_preview.png","Graphite":"https://files.cdn.printful.com/files/6cd/6cd3db0a229d5094f9a3c1a21b908d37_preview.png","Navy":"https://files.cdn.printful.com/files/6c4/6c4384d1124be5f9cfbc601d94089f10_preview.png","Midnight":"https://files.cdn.printful.com/files/cf7/cf7f7cfaf86e55e8a043db09622daabe_preview.png","Hemp":"https://files.cdn.printful.com/files/889/889cd125be6fff27496683e6c0b50450_preview.png","Pepper":"https://files.cdn.printful.com/files/6dc/6dc7caecc450ef8ce40d138e31ed8107_preview.png","Grape":"https://files.cdn.printful.com/files/598/5983853253599e04f833b840853343fa_preview.png","Denim":"https://files.cdn.printful.com/files/d60/d60f8a4f1e2441097b79c2681207aac0_preview.png","Blue Spruce":"https://files.cdn.printful.com/files/728/7281042fb79aef31076b5cdc1d85fc09_preview.png","Crimson":"https://files.cdn.printful.com/files/479/479fd6782f5b1d40bd2dab39dbf88e5a_preview.png","Flo Blue":"https://files.cdn.printful.com/files/f58/f58cf76a759b445132933feac5c13779_preview.png","Violet":"https://files.cdn.printful.com/files/854/8547f3e7e31e366ee0c52f010dcb8049_preview.png"},
    colors: ["Black","True Navy","Red","Graphite","Navy","Midnight","Hemp","Pepper","Grape","Denim","Blue Spruce","Crimson","Flo Blue","Violet"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "A2", series: "A — Storytelling Era", tribe: "gm", tribeLabel: "GM",
    title: "1970", slogan: "1970. The last year they let them build it like that.", sub: "",
    desc: "Chevelle SS 454 silhouette with distressed typography. Asphalt black tee.",
    price: 34.99, swatch: "1970", image: "https:\/\/files.cdn.printful.com\/files\/401\/401d60fc2be69418a9a15824fe46c7fa_preview.png", images: {"Black":"https://files.cdn.printful.com/files/401/401d60fc2be69418a9a15824fe46c7fa_preview.png","True Navy":"https://files.cdn.printful.com/files/69c/69cd4d7e6dcd07eef74abedcfa0d0daa_preview.png","Red":"https://files.cdn.printful.com/files/129/129edac603a6ae112b049ec226010c8c_preview.png","Graphite":"https://files.cdn.printful.com/files/f7d/f7d26262ebba662b2e932fb8ee1e2f54_preview.png","Navy":"https://files.cdn.printful.com/files/dcd/dcd2f0e64279edcaa2e8fe35570209d9_preview.png","Midnight":"https://files.cdn.printful.com/files/7f8/7f863644c28ebb250decdd265802d9ec_preview.png","Hemp":"https://files.cdn.printful.com/files/485/485d09a5779978534d45efbfbf481b85_preview.png","Crimson":"https://files.cdn.printful.com/files/c46/c464be7e80a673f12a74668fc8712e88_preview.png","Yam":"https://files.cdn.printful.com/files/5c1/5c1a61420da16573873549dcc234d3b4_preview.png","Seafoam":"https://files.cdn.printful.com/files/c40/c405cb8d64dd7ade47a830e79faef2d8_preview.png"},
    colors: ["Black","True Navy","Red","Graphite","Navy","Midnight","Hemp","Crimson","Yam","Seafoam"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "A3", series: "A — Storytelling Era", tribe: "cross", tribeLabel: "Cross-Tribe",
    title: "1971", slogan: "1971. The last V8 before the lawyers showed up.", sub: "",
    desc: "Circular vintage race-badge print, aged bronze on black.",
    price: 34.99, swatch: "1971", image: "https:\/\/files.cdn.printful.com\/files\/870\/870edea0bbf03bb0ec530202f53b03b0_preview.png", images: {"Mystic Blue":"https://files.cdn.printful.com/files/870/870edea0bbf03bb0ec530202f53b03b0_preview.png","Light Green":"https://files.cdn.printful.com/files/b89/b8938e28ac95cfaacac337a8303703f9_preview.png","Blue Jean":"https://files.cdn.printful.com/files/ff6/ff607f756020727783dc34cab4f87fd7_preview.png","Crunchberry":"https://files.cdn.printful.com/files/d45/d45bc5d29839b83bbf11ce92e61a9b46_preview.png","Seafoam":"https://files.cdn.printful.com/files/7b5/7b5cdaecc84cb4e6ca4576497dec1cfe_preview.png","Terracotta":"https://files.cdn.printful.com/files/1ea/1ea39272465cd6af4cf3e3b41ce0c555_preview.png","Chalky Mint":"https://files.cdn.printful.com/files/025/025bccb3d9d6e67ee8ea47707eaedaf7_preview.png","White":"https://files.cdn.printful.com/files/76c/76c735e37830a2a30c6c19341e99fea6_preview.png"},
    colors: ["Mystic Blue","Light Green","Blue Jean","Crunchberry","Seafoam","Terracotta","Chalky Mint","White"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "A4", series: "A — Storytelling Era", tribe: "cross", tribeLabel: "Cross-Tribe",
    title: "Displacement", slogan: "1970. When displacement was still the answer.", sub: "396 · 427 · 440 · 454 · 426",
    desc: "Vertical cascade of legendary cubic-inch numbers over distressed black.",
    price: 34.99, swatch: "396+", image: "https:\/\/files.cdn.printful.com\/files\/d3c\/d3c18232258230ae1a6478eb053be8da_preview.png", images: {"Black":"https://files.cdn.printful.com/files/d3c/d3c18232258230ae1a6478eb053be8da_preview.png","True Navy":"https://files.cdn.printful.com/files/3d2/3d24fd575bb48dcb3e6cc0f6a9f88788_preview.png","Flo Blue":"https://files.cdn.printful.com/files/e92/e929317c89ad6b11e326516f7192a47c_preview.png","Burnt orange":"https://files.cdn.printful.com/files/4f0/4f072487aff2d044b5fa2165a5a5d4b1_preview.png","Violet":"https://files.cdn.printful.com/files/b8e/b8e057d92fd41412f6b04fb5c6d16cc9_preview.png","White":"https://files.cdn.printful.com/files/317/317fbcfca8b81a5cae2a61a1e5c898d3_preview.png"},
    colors: ["Black","True Navy","Flo Blue","Burnt orange","Violet","White"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "B1", series: "B — Voice of the Mechanic", tribe: "cross", tribeLabel: "Cross-Tribe",
    title: "I Built It", slogan: "I don't drive it. I built it.", sub: "",
    desc: "Old-school tattoo flash — mechanic's hands gripping a wrench.",
    price: 34.99, swatch: "BUILT", image: "https:\/\/files.cdn.printful.com\/files\/c6a\/c6a88dcf73041ca32e2644cc43854327_preview.png", images: {"Black":"https://files.cdn.printful.com/files/c6a/c6a88dcf73041ca32e2644cc43854327_preview.png","Red":"https://files.cdn.printful.com/files/f30/f30afe77ac87889b0b33f5ef7d4a3fd9_preview.png","Midnight":"https://files.cdn.printful.com/files/eab/eab3fe0624839f29eafb22234462590d_preview.png","Crimson":"https://files.cdn.printful.com/files/ead/ead38f11689aca7917fc59bcd3db1710_preview.png","Flo Blue":"https://files.cdn.printful.com/files/a97/a9798ce2daa4860929c2696cef205259_preview.png","Yam":"https://files.cdn.printful.com/files/e58/e58db13d613f75e44c2d029b55f87211_preview.png","Crunchberry":"https://files.cdn.printful.com/files/8ce/8cec912f4c0c2587760372bbda29820b_preview.png","White":"https://files.cdn.printful.com/files/8dc/8dce68690dbc120397dd8d31dadaf1dd_preview.png"},
    colors: ["Black","Red","Midnight","Crimson","Flo Blue","Yam","Crunchberry","White"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "B2", series: "B — Voice of the Mechanic", tribe: "cross", tribeLabel: "Cross-Tribe",
    title: "Torque Wrench", slogan: "The torque wrench doesn't lie.", sub: "Neither does the dyno.",
    desc: "Minimalist tool icon with stencil typography on charcoal.",
    price: 34.99, swatch: "TORQ", image: "https:\/\/files.cdn.printful.com\/files\/0b0\/0b0a35e1d50240770ab838c2e1301c8f_preview.png", images: {"Black":"https://files.cdn.printful.com/files/0b0/0b0a35e1d50240770ab838c2e1301c8f_preview.png","True Navy":"https://files.cdn.printful.com/files/591/59162cac7fc469a1795ed531ec3917bf_preview.png","Red":"https://files.cdn.printful.com/files/2f3/2f3c0a28118150b81b93b5a88b2f81e9_preview.png","Midnight":"https://files.cdn.printful.com/files/2a2/2a2e5c93a35fd4cdeeb19f1e43297a25_preview.png","Pepper":"https://files.cdn.printful.com/files/6be/6be0499551d1cc983d3c207b00d12b50_preview.png","Blue Spruce":"https://files.cdn.printful.com/files/3f2/3f21376d9ffa57ca16cdbe564321dd5c_preview.png","Flo Blue":"https://files.cdn.printful.com/files/d04/d04e66172db18e068440b4bd4420b3df_preview.png"},
    colors: ["Black","True Navy","Red","Midnight","Pepper","Blue Spruce","Flo Blue"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "B3", series: "B — Voice of the Mechanic", tribe: "cross", tribeLabel: "Cross-Tribe",
    title: "Built in a Garage", slogan: "Built in a garage. Not on an assembly line.", sub: "",
    desc: "Vintage workwear patch badge, ivory and blood red on black.",
    price: 34.99, swatch: "GRGE", image: "https:\/\/files.cdn.printful.com\/files\/60f\/60f945dbac479b4888e283719740b25d_preview.png", images: {"Red":"https://files.cdn.printful.com/files/60f/60f945dbac479b4888e283719740b25d_preview.png","Denim":"https://files.cdn.printful.com/files/5fa/5fac88408724c2153637a3871a9627a5_preview.png","Paprika":"https://files.cdn.printful.com/files/e82/e824719b7834cf9d697f6e907b26059d_preview.png","Crunchberry":"https://files.cdn.printful.com/files/546/5463398a93a771137593b06b9475b6fa_preview.png","Seafoam":"https://files.cdn.printful.com/files/54d/54d8c87303f7949ed6b1264f76c19c30_preview.png","Granite":"https://files.cdn.printful.com/files/eb1/eb1cd415a638897bd1eb6f2029d6a718_preview.png","Washed Denim":"https://files.cdn.printful.com/files/85e/85eb6132c7394c1f24554b9da4c922e7_preview.png","White":"https://files.cdn.printful.com/files/697/697e2442988342f2f62b7ea08d220d3c_preview.png"},
    colors: ["Red","Denim","Paprika","Crunchberry","Seafoam","Granite","Washed Denim","White"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "B4", series: "B — Voice of the Mechanic", tribe: "cross", tribeLabel: "Cross-Tribe",
    title: "My Hands Know", slogan: "My hands know this engine better than its factory did.", sub: "",
    desc: "V8 silhouette with faint handprint watermark, large format print.",
    price: 34.99, swatch: "V8", image: "https:\/\/files.cdn.printful.com\/files\/7ce\/7ce5c8c1ffeaf431888e8e57a8cc9eed_preview.png", images: {"Black":"https://files.cdn.printful.com/files/7ce/7ce5c8c1ffeaf431888e8e57a8cc9eed_preview.png","True Navy":"https://files.cdn.printful.com/files/099/099c584c890b32245e2d5718b27820b3_preview.png","Graphite":"https://files.cdn.printful.com/files/5d6/5d663af362ef39f2938c96abbfc5e7ac_preview.png","Navy":"https://files.cdn.printful.com/files/9c7/9c738fee687d2b355a0c403d63c4d76d_preview.png","Midnight":"https://files.cdn.printful.com/files/9ab/9ab809f2a507911f3b8b1f7fbfd6b28e_preview.png","Hemp":"https://files.cdn.printful.com/files/565/565ecb34699eefcfb95869d51f1c8f96_preview.png","Bay":"https://files.cdn.printful.com/files/d20/d200e3eace7e470c24b7d84daa6a0dbc_preview.png"},
    colors: ["Black","True Navy","Graphite","Navy","Midnight","Hemp","Bay"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "C1", series: "C — Tribal Rivalry", tribe: "cross", tribeLabel: "Cross-Tribe",
    title: "Three Tribes", slogan: "Ford guys wave. Mopar guys nod. Chevy guys just stare.", sub: "",
    desc: "Three-line typographic hierarchy, each tribe in its own font and color.",
    price: 34.99, swatch: "3X", image: "https:\/\/files.cdn.printful.com\/files\/f86\/f8696dd9f6e6e46b0e5696e7ee845830_preview.png", images: {"Black":"https://files.cdn.printful.com/files/f86/f8696dd9f6e6e46b0e5696e7ee845830_preview.png","True Navy":"https://files.cdn.printful.com/files/39d/39d55d5cd0cfe1f1ffbe9ef904296b4d_preview.png","Red":"https://files.cdn.printful.com/files/859/859fa0b5760ad90045f4cb4fd5db386e_preview.png","Graphite":"https://files.cdn.printful.com/files/b4f/b4f9474b95149f168a596d535e5e4790_preview.png","Navy":"https://files.cdn.printful.com/files/0e2/0e24a3af8188669ab1ac053e088626ff_preview.png","Denim":"https://files.cdn.printful.com/files/ac2/ac2e185b9fe86a9c2595a128de9641c6_preview.png","Blue Spruce":"https://files.cdn.printful.com/files/4c6/4c6d74d2444abde5865825e4cf3a36c9_preview.png","Moss":"https://files.cdn.printful.com/files/08d/08ddcac7023f6fea59c88c0c82ac812e_preview.png","Yam":"https://files.cdn.printful.com/files/6d1/6d1d01ff884a9bce52eb0485f445eede_preview.png","White":"https://files.cdn.printful.com/files/6e0/6e04b23ffec009e552d0c6d06632e760_preview.png"},
    colors: ["Black","True Navy","Red","Graphite","Navy","Denim","Blue Spruce","Moss","Yam","White"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "C2", series: "C — Tribal Rivalry", tribe: "mopar", tribeLabel: "Mopar",
    title: "I Have a Charger", slogan: "I don't have a favorite. I have a Charger.", sub: "Also available: Mustang / Chevelle",
    desc: "Distressed newspaper-headline print, italic display type.",
    price: 34.99, swatch: "CHRG", image: null, images: {},
    colors: [], sizes: [],
    comingSoon: true
  },
  {
    id: "C3", series: "C — Tribal Rivalry", tribe: "cross", tribeLabel: "Cross-Tribe",
    title: "One Asphalt", slogan: "Three tribes. One asphalt.", sub: "",
    desc: "Circular badge with three silhouettes — Mustang, Charger, Chevelle.",
    price: 34.99, swatch: "1AS", image: "https:\/\/files.cdn.printful.com\/files\/328\/328c72435edb3690e79d837e7d1171ab_preview.png", images: {"Black":"https://files.cdn.printful.com/files/328/328c72435edb3690e79d837e7d1171ab_preview.png","True Navy":"https://files.cdn.printful.com/files/d7d/d7d89d5da9935d95bf38eff707a69824_preview.png","Red":"https://files.cdn.printful.com/files/f8b/f8b35c153fdd6f35116556127a256ea5_preview.png","Hemp":"https://files.cdn.printful.com/files/c58/c582f7a2fbd4cfc2b2d6412e0d8a840f_preview.png","Brick":"https://files.cdn.printful.com/files/b5f/b5f178af102964b18ed6ee1595fe8834_preview.png","Light Green":"https://files.cdn.printful.com/files/2be/2bea077cfd6693ab29b70da81f74e677_preview.png","Yam":"https://files.cdn.printful.com/files/1ec/1ecc8176d52af4b671a3a50832be016d_preview.png","Blue Jean":"https://files.cdn.printful.com/files/6f8/6f88c27a68e56d0a40876103ecd6869d_preview.png","White":"https://files.cdn.printful.com/files/95c/95c174db57970260a621796c70fdaa26_preview.png"},
    colors: ["Black","True Navy","Red","Hemp","Brick","Light Green","Yam","Blue Jean","White"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "D1", series: "D — The Engine as Hero", tribe: "mopar", tribeLabel: "Mopar",
    title: "426 Hemi", slogan: "426 Hemi. Born to Terrorize.", sub: "",
    desc: "Technical blueprint exploded view, white lines on black. Premium print.",
    price: 37.99, swatch: "426", image: "https:\/\/files.cdn.printful.com\/files\/dc9\/dc90197313978d7a928f48403afb6710_preview.png", images: {"Black":"https://files.cdn.printful.com/files/dc9/dc90197313978d7a928f48403afb6710_preview.png","True Navy":"https://files.cdn.printful.com/files/cdf/cdf504e6e60a854f295a35b3b7cb810e_preview.png","Sage":"https://files.cdn.printful.com/files/2d3/2d3012873312665f1ebacb3ca31ecadb_preview.png","Midnight":"https://files.cdn.printful.com/files/03d/03de51ea77969b314a31f0823057d59c_preview.png","Pepper":"https://files.cdn.printful.com/files/016/016c1515aa8ffc34484d4511178d62e0_preview.png","Berry":"https://files.cdn.printful.com/files/51c/51c2ff7baec6874798bc06a4ccab6512_preview.png","Espresso":"https://files.cdn.printful.com/files/90d/90d07c6a9c60a3bd332daacc865c5c35_preview.png","Light Green":"https://files.cdn.printful.com/files/123/12332593571ee1bbcc606d50d4c54472_preview.png","Blue Jean":"https://files.cdn.printful.com/files/57e/57e17406d83bdd243f2b4ba3f50d558c_preview.png","Grey":"https://files.cdn.printful.com/files/20b/20bee7f80be9c78cade1a6e54f9f32e6_preview.png"},
    colors: ["Black","True Navy","Sage","Midnight","Pepper","Berry","Espresso","Light Green","Blue Jean","Grey"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "D2", series: "D — The Engine as Hero", tribe: "gm", tribeLabel: "GM",
    title: "LS6", slogan: "LS6. The Engine That Ended the Argument.", sub: "",
    desc: "Old-school tattoo flash treatment, flames framing the block.",
    price: 34.99, swatch: "LS6", image: "https:\/\/files.cdn.printful.com\/files\/86d\/86d4e751d0e91a4a9a247753abd022e8_preview.png", images: {"Black":"https://files.cdn.printful.com/files/86d/86d4e751d0e91a4a9a247753abd022e8_preview.png","True Navy":"https://files.cdn.printful.com/files/0b6/0b67417ef2b0bcb5d46ec0c1cb5631dc_preview.png","Red":"https://files.cdn.printful.com/files/60c/60ce5c359c969b433d12f57afeb86f6c_preview.png","Navy":"https://files.cdn.printful.com/files/272/2729beb98a6666b0d4a687b52646304a_preview.png","Midnight":"https://files.cdn.printful.com/files/274/2749ac0308da49888c4ed67f9b530689_preview.png","Denim":"https://files.cdn.printful.com/files/4e2/4e248eba35b64af75246432a5d07dc90_preview.png","Crimson":"https://files.cdn.printful.com/files/d3f/d3f9a2b484eb0d5846738ddf7d0e3e27_preview.png","Moss":"https://files.cdn.printful.com/files/7db/7dbe96cf9ad776d90c517484b9f760a0_preview.png","Seafoam":"https://files.cdn.printful.com/files/a47/a4772581565d5cd2a3099d67404620f1_preview.png","Island Reef":"https://files.cdn.printful.com/files/c3a/c3a1fefebc849e77b2eec0db30913b5e_preview.png","White":"https://files.cdn.printful.com/files/35b/35bb133f0ab02ab506b8cbdf1868726d_preview.png"},
    colors: ["Black","True Navy","Red","Navy","Midnight","Denim","Crimson","Moss","Seafoam","Island Reef","White"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "D3", series: "D — The Engine as Hero", tribe: "ford", tribeLabel: "Ford",
    title: "Boss 429", slogan: "Boss 429. NASA Couldn't Stop It Either.", sub: "",
    desc: "1960s race-poster typography, cobalt accent on black.",
    price: 34.99, swatch: "429", image: "https:\/\/files.cdn.printful.com\/files\/68b\/68b9d75dc94063a6dd0c679457b84871_preview.png", images: {"Black":"https://files.cdn.printful.com/files/68b/68b9d75dc94063a6dd0c679457b84871_preview.png","True Navy":"https://files.cdn.printful.com/files/ff0/ff03c925982951de27236f6c7ec02c9b_preview.png","Sage":"https://files.cdn.printful.com/files/93c/93c082ab0bd145b9f818f730d28088e0_preview.png","Moss":"https://files.cdn.printful.com/files/f9d/f9dac11a0c22e2c2edaf74844b1eac84_preview.png","Light Green":"https://files.cdn.printful.com/files/61d/61dcc7e56c0eea3aba83da0c572a46e2_preview.png","Seafoam":"https://files.cdn.printful.com/files/df5/df5bb551cb0f62c880094ee496e77632_preview.png"},
    colors: ["Black","True Navy","Sage","Moss","Light Green","Seafoam"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "X1", series: "Cross-Tribe Bonus", tribe: "cross", tribeLabel: "Cross-Tribe",
    title: "Four on the Floor", slogan: "Four on the floor. No excuses.", sub: "",
    desc: "Hurst 4-speed shift pattern, precision line art on black.",
    price: 34.99, swatch: "4SPD", image: "https:\/\/files.cdn.printful.com\/files\/fa5\/fa5ef560f11cc531d8cdc6a71572b4e0_preview.png", images: {"Black":"https://files.cdn.printful.com/files/fa5/fa5ef560f11cc531d8cdc6a71572b4e0_preview.png","True Navy":"https://files.cdn.printful.com/files/c88/c88d06c5ee19555c9070e0f8917b6584_preview.png","Sage":"https://files.cdn.printful.com/files/f00/f00c0fad22ea9f3e67d6fc82f78a0d4a_preview.png","Brick":"https://files.cdn.printful.com/files/633/6335672bd27ea2152c7067b86abee289_preview.png","Blue Jean":"https://files.cdn.printful.com/files/79f/79f374f35b9e415b83bd34793dff4aa6_preview.png","Grey":"https://files.cdn.printful.com/files/f39/f39f71b9b964fd442af85d6cec51bd93_preview.png","Seafoam":"https://files.cdn.printful.com/files/9ed/9ed366d544e7e5b12a10c96c4d561cbb_preview.png"},
    colors: ["Black","True Navy","Sage","Brick","Blue Jean","Grey","Seafoam"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "C2-ford", series: "C — Tribal Rivalry", tribe: "ford", tribeLabel: "Ford",
    title: "I Have a Mustang", slogan: "I don't have a favorite. I have a Mustang.", sub: "",
    desc: "Distressed newspaper-headline style typography, Mustang edition. Comfort Colors 1717, garment-dyed heavyweight.",
    price: 34.99, swatch: "MSTG", image: "https:\/\/files.cdn.printful.com\/files\/81a\/81a31ae61fc24be8f4b825e380ddd11e_preview.png", images: {"Black":"https://files.cdn.printful.com/files/81a/81a31ae61fc24be8f4b825e380ddd11e_preview.png","True Navy":"https://files.cdn.printful.com/files/a79/a794a5662721c5f435b3fb2cb245ef1a_preview.png","Sage":"https://files.cdn.printful.com/files/ab2/ab24cc47abc972ef829144823870b6a1_preview.png","Midnight":"https://files.cdn.printful.com/files/a38/a38249e3994ed26c5a5181f912f85914_preview.png","Blue Spruce":"https://files.cdn.printful.com/files/402/40295ee9fcb11f3672f8d0c66002b3e4_preview.png","Light Green":"https://files.cdn.printful.com/files/e93/e93e36f46c89197d8c6015578b5331f3_preview.png","Yam":"https://files.cdn.printful.com/files/12b/12bffd386704d81dc9ea6333d5015744_preview.png","Blue Jean":"https://files.cdn.printful.com/files/a9a/a9aa305a30b289533218b29e258f2a1e_preview.png"},
    colors: ["Black","True Navy","Sage","Midnight","Blue Spruce","Light Green","Yam","Blue Jean"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "D3-hoodie", series: "D — The Engine as Hero", tribe: "ford", tribeLabel: "Ford",
    title: "Boss 429 Hoodie", slogan: "Boss 429. NASA Couldn't Stop It Either.", sub: "",
    desc: "Same design as the Boss 429 tee, hoodie cut.",
    price: 34.99, swatch: "429-H", image: "https:\/\/files.cdn.printful.com\/files\/f91\/f91a48c23bace11e44b21b67413d9fbf_preview.png", images: {"Black":"https://files.cdn.printful.com/files/f91/f91a48c23bace11e44b21b67413d9fbf_preview.png","Forest Green":"https://files.cdn.printful.com/files/db3/db3f5d545510b9e9b44676413c03778f_preview.png","Adobe":"https://files.cdn.printful.com/files/267/267fbc0b91b960c7ab839356fdc2b196_preview.png"},
    colors: ["Black","Forest Green","Adobe"],
    sizes: ["S","M","L","XL","2XL","3XL"]
  },
  {
    id: "A5", series: "A — Storytelling Era", tribe: "ford", tribeLabel: "Ford",
    title: "Vintage Sunset Retro 1968 Mustang", slogan: "1968. The fastback that rewrote the rules.", sub: "",
    desc: "Vintage sunset retro design — 1968 Mustang fastback silhouette on distressed sunset circle. Comfort Colors 1717, garment-dyed heavyweight.",
    price: 34.99, swatch: "A5", image: "https:\/\/files.cdn.printful.com\/files\/24c\/24c9b59a39fdd6f5bd54f7b5d96e9ea6_preview.png", images: {"Black":"https://files.cdn.printful.com/files/24c/24c9b59a39fdd6f5bd54f7b5d96e9ea6_preview.png","True Navy":"https://files.cdn.printful.com/files/38c/38cc9aa7f3c2c7224734726943b49dda_preview.png","Midnight":"https://files.cdn.printful.com/files/d7a/d7ab6a08b5b8ffc4cfcc130b94e1f593_preview.png","Denim":"https://files.cdn.printful.com/files/eaa/eaafa48a716ab7859fcffe724dbaea24_preview.png","Flo Blue":"https://files.cdn.printful.com/files/8c3/8c3ee2645b7e2eecf46cbe46ada1158f_preview.png","Light Green":"https://files.cdn.printful.com/files/fcc/fccba95b55d04fbe9c6e6ca42860296c_preview.png","Blue Jean":"https://files.cdn.printful.com/files/782/782c4aca1c3e659566fb3ef59e12d5f0_preview.png","Violet":"https://files.cdn.printful.com/files/836/8366d6f0c54f4a14f99a73ef9a614c3b_preview.png","Seafoam":"https://files.cdn.printful.com/files/9ff/9ff2270ac73ae4c95d3a624d0e733681_preview.png","Orchid":"https://files.cdn.printful.com/files/c8f/c8fd6f22e405da81b3d76bc780620877_preview.png","Ivory":"https://files.cdn.printful.com/files/4b1/4b1baee3333d9d9a324a9de0c6d11e89_preview.png","White":"https://files.cdn.printful.com/files/92b/92ba81af6d988f54fbc8738d5e65717e_preview.png"},
    colors: ["Black","True Navy","Midnight","Denim","Flo Blue","Light Green","Blue Jean","Violet","Seafoam","Orchid","Ivory","White"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  },
  {
    id: "C2-gm", series: "C — Tribal Rivalry", tribe: "gm", tribeLabel: "GM",
    title: "I Have a Chevelle", slogan: "I don't have a favorite. I have a Chevelle.", sub: "",
    desc: "Distressed newspaper-headline style typography, Chevelle edition. Comfort Colors 1717, garment-dyed heavyweight.",
    price: 34.99, swatch: "C2-gm", image: "https:\/\/files.cdn.printful.com\/files\/6f9\/6f95f476b89ad8491fa25bf87ccf91bd_preview.png", images: {"Black":"https://files.cdn.printful.com/files/6f9/6f95f476b89ad8491fa25bf87ccf91bd_preview.png","True Navy":"https://files.cdn.printful.com/files/80a/80a57cc7ba4283bce061e1645736e0f2_preview.png","Red":"https://files.cdn.printful.com/files/d57/d5767c4cbe33920ed4f841c86b38662c_preview.png","Midnight":"https://files.cdn.printful.com/files/8c2/8c203730c2676e57381728a4f6935826_preview.png","Brick":"https://files.cdn.printful.com/files/105/1057414881092e9b42469869376c4563_preview.png","Espresso":"https://files.cdn.printful.com/files/6f0/6f01fd334ee0b32ce6a6889e4e8cc298_preview.png","Moss":"https://files.cdn.printful.com/files/05d/05d85782544d3d607aac84708df9464c_preview.png","Yam":"https://files.cdn.printful.com/files/c7a/c7a89b990c4cd48814504fc0979ec75f_preview.png","Mustard":"https://files.cdn.printful.com/files/459/459de880a14330fe11253e374abbf368_preview.png","Butter":"https://files.cdn.printful.com/files/19f/19f01da4ffc5c3d9d269060225333275_preview.png","Chambray":"https://files.cdn.printful.com/files/17f/17fb1e344bff7c5b37d5ae0e384ccda3_preview.png","White":"https://files.cdn.printful.com/files/6b3/6b38072840b004b658ba53e0adcd7518_preview.png"},
    colors: ["Black","True Navy","Red","Midnight","Brick","Espresso","Moss","Yam","Mustard","Butter","Chambray","White"],
    sizes: ["S","M","L","XL","2XL","3XL","4XL"]
  }
];
