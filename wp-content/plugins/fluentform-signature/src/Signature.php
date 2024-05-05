<?php

namespace FluentFormSignature;

use FluentForm\Framework\Helpers\ArrayHelper as Arr;

class Signature
{
    /**
     * The upload directory for this WordPress installation.
     *
     * @var array
     */
    protected $wpUploadDirectory;

    /**
     * The upload directory for fluentform.
     *
     * @var string
     */
    protected $directory;

    /**
     * The base url for the signatures.
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Construct the Signature instance.
     */
    public function __construct()
    {
        // Ensure the signatures upload directory.
        $this->ensureUploadDirectory();
    }

    /**
     * Prepare the signatures for the appropriate form fields.
     *
     * @param  array $formData
     * @param  int   $formId
     * @param  array $inputConfigs
     * @return array $formData
     */
    public function add($formData, $formId, $inputConfigs)
    {
        foreach ($formData as $name => &$value) {
            $inputType = Arr::get($inputConfigs, $name.'.element');

            if ($inputType == 'signature' && $value) {
                $value = $this->make($value, $formId);
            }
        }

        return $formData;
    }

    /**
     * Make a signature image from the data URI
     * string and then return the signature URL.
     *
     * @param  string $dataUri
     * @param  int    $formId
     * @return string
     */
    public function make($dataUri, $formId)
    {
        $encodedImage = explode(',', $dataUri)[1];

        $decodedImage = base64_decode($encodedImage);

        $fileName = $this->generateFileName($formId);

        file_put_contents($this->directory.'/'.$fileName, $decodedImage);

        return $this->baseUrl.'/'.$fileName;
    }

    /**
     * Ensure the upload directory for the signatures.
     *
     * @return $this
     */
    private function ensureUploadDirectory()
    {
        // Cache the WordPress upload directory.
        $this->wpUploadDirectory = wp_upload_dir();

        // The intended upload directory for signatures in fluentform.
        $directory = $this->wpUploadDirectory['basedir'].'/fluentform/signatures';

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);

            // Upon creating the upload directory we have to ensure the
            // prevention of manually executing files in Apache server.
            $htaccess = file_get_contents(__DIR__.'/stubs/htaccess.stub');
            file_put_contents($directory.'/.htaccess', $htaccess);
        }
        // Cache the intended upload directory and url.
        $this->directory = $directory;
        $this->baseUrl = $this->wpUploadDirectory['baseurl'].'/fluentform/signatures';

        return $this;
    }

    /**
     * Generate the signature filename.
     *
     * @param  int    $formId
     * @return string $fileName
     */
    private function generateFileName($formId)
    {
        $protection = md5(time().'-'.rand(0, 1000));

        $fileName = 'signature-'.$protection.'.png';

        $fileName = apply_filters_deprecated(
            'fluentform-signature-filename',
            [
                $fileName,
                $formId
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/signature_filename',
            'Use fluentform/signature_filename instead of fluentform-signature-filename.'
        );

        return apply_filters('fluentform/signature_filename', $fileName, $formId);
    }
}
