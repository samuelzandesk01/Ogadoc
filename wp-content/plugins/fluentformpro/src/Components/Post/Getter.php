<?php

namespace FluentFormPro\Components\Post;


use FluentForm\App\Api\FormProperties;
use FluentForm\App\Helpers\Helper;
use FluentForm\Framework\Helpers\ArrayHelper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

trait Getter
{
    /**
     * Post meta helper
     * @param $url string
     * @return array | false deleted attachment's id false on fail
     */
    private static function maybeRemoveMetaImages($url)
    {
        if (false === strpos($url, '__acf_image_or_file_remove__')) {
           return false;
        }
        // should remove attachment
        // when remove image/file/gallery, attachment id/ids added after '__acf_image_or_file_remove__'.
        $deletedIds = [];
        $urlArr = explode('__acf_image_or_file_remove__', $url);
        if (count($urlArr) > 1 && $attachments = $urlArr[1]) {
            $attachmentIds = explode(',', $attachments);
            foreach ($attachmentIds as $id) {
                if (!$id) continue;
                if (wp_delete_attachment((int)$id)){
                    $deletedIds[] = $id;
                }
            }
        }
        return $deletedIds ?: false;
    }

    private static function resolveCustomMetaFileTypeField($customMetas, $form, $formData)
    {
        if (!$customMetas || !$form || !$formData) return $customMetas;

        $formFields = (new FormProperties($form))->inputs(['attributes']);
        foreach ($customMetas as $index => $field) {
            $name = Helper::getInputNameFromShortCode(ArrayHelper::get($field, 'meta_value', ''));
            if (!$name) continue;
            $formField = ArrayHelper::get($formFields, $name);
            if (
                'file' == ArrayHelper::get($formField, 'attributes.type') &&
                !ArrayHelper::get($formData, $name)
            ) {
                ArrayHelper::forget($customMetas, $index);
            }
        }
        return $customMetas;
    }

}