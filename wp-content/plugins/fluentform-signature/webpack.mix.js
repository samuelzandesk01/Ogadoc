const mix = require("laravel-mix");

mix
    .js(
        "src/assets/js/fluentform-signature.js",
        "public/js/fluentform-signature.js"
    )
    .copy(
        "src/assets/js/signature_pad.min.js",
        "public/js/signature_pad.min.js"
    )
    .copy("src/assets/js/signature_pad.js", "public/js/signature_pad.js")
    .sass("src/assets/scss/styles.scss", "public/css/fluentform-signature.css");
