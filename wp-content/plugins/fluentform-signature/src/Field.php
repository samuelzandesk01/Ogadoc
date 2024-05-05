<?php

namespace FluentFormSignature;

use FluentForm\App\Services\FormBuilder\Components\BaseComponent;

class Field extends BaseComponent
{
    /**
     * The signature field compiler.
     *
     * @param array     $data
     * @param \stdClass $form
     */
    public function compile($data, $form)
    {
        $elMarkup = $this->getHtml(__DIR__.'/resources/field.php', ['data' => $data, 'form' => $form]);

        echo $this->buildElementMarkup($elMarkup, $data, $form);

        $this->enqueuePublicScripts();
    }

    /**
     * Enqueue the scripts on the form page.
     */
    private function enqueuePublicScripts()
    {
        wp_enqueue_style(
            'fluentform-signature',
            FLUENTFORM_SIGNATURE_URL.'public/css/fluentform-signature.css',
            [],
            FLUENTFORM_SIGNATURE_VERSION
        );

        wp_enqueue_script(
            'signature_pad',
            FLUENTFORM_SIGNATURE_URL.'public/js/signature_pad.js',
            ['jquery'],
            '2.3.2',
            true
        );

        wp_enqueue_script(
            'fluentform-signature',
            FLUENTFORM_SIGNATURE_URL.'public/js/fluentform-signature.js',
            ['jquery', 'fluent-form-submission'],
            FLUENTFORM_SIGNATURE_VERSION,
            true
        );
    }

    /**
     * Get the rendered html from a file.
     *
     * @param  string $file
     * @param  array  $data
     * @return string
     */
    private function getHtml($file, $data = [])
    {
        ob_start();
        extract($data);
        include $file;
        return ob_get_clean();
    }
}
