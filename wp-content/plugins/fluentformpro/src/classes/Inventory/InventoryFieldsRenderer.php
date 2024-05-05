<?php

namespace FluentFormPro\classes\Inventory;

use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\App\Services\Report\ReportHelper;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;

class InventoryFieldsRenderer
{
    public function adjustOptions($attr)
    {
        $field = $attr['field'];
        
        $isPaymentInput = Arr::get($field, 'settings.is_payment_field') == 'yes';
        if ($isPaymentInput) {
            $optionKey = 'pricing_options';
        } else {
            $optionKey = 'advanced_options';
        }
        $options = Arr::get($field, 'settings.' . $optionKey);
        if (empty($options)) {
            return $options;
        }
        
        $maybeAllOptionStockOut = 'yes';
        foreach ($options as $key => $option) {
            $item = [
                'parent_input_name' => Arr::get($field, 'attributes.name'),
                'item_name'         => Arr::get($option, 'label'),
                'item_value'        => Arr::get($option, 'value'),
                'quantity'          => Arr::get($option, 'quantity'),
            ];
            if ($isPaymentInput) {
                $itemPrice = \FluentFormPro\Payments\PaymentHelper::convertToCents($item['item_value']);
                $used = InventoryValidation::getPaymentItemSubmissionQuantity($attr['formId'], $item['parent_input_name'],
                    $item['item_name'], $itemPrice);
            } else {
                $used = InventoryValidation::getRegularItemUsedQuantity($attr['previousSubmissionData'], $item);
            }
            $quantity = isset($option['quantity']) ? $option['quantity'] : false;
            if (!$quantity) {
                $maybeAllOptionStockOut = 'no';
                continue;
            }
            //if value is negative returns 0
            $remaining = max($quantity - $used, 0);
            if ($attr['showStock'] && $isPaymentInput) {
                $options[$key]['quantiy_label'] = str_replace('{remaining_quantity}', $remaining, $attr['stockLabel']);
            } elseif ($attr['showStock']) {
                $options[$key]['label'] .= str_replace('{remaining_quantity}', $remaining, $attr['stockLabel']);
            }
            
            if ($attr['hideChoice'] && $remaining == 0) {
                unset($options[$key]);
            }
            
            if ($remaining > 0) {
                $maybeAllOptionStockOut = 'no';
            }
            //maybe disable option stock out item
            $disableStockOut = Arr::get($field, 'settings.disable_input_when_stockout') == 'yes';
            if($disableStockOut && $remaining <=0 ){
                $options[$key]['disabled'] = true;
            }
    
        }
    
    
        // Hide Inputs When All Option Is Stock-out
        $hideStockInput = Arr::get($field, 'settings.hide_input_when_stockout') == 'yes';
        if ($maybeAllOptionStockOut == 'yes' && $hideStockInput) {
            $field['settings']['container_class'] .= 'has-conditions ff_excluded ';
            
            //condition to return false always if Stock-out for conversational form
            $field['settings']['conditional_logics'] =[
                'status' => true,
                'type' => 'all',
                'conditions' =>[[
                    'field' => Arr::get($field, 'attributes.name'),
                    'operator' => '!=',
                    'value' => null
                ]]
            ];
    
        }
    
        $field['settings.' . $optionKey] = array_values($options);
        return $field;
    }
    
    public function adjustSinglePaymentItem($field, $form, $stockLabel)
    {
        $availableQuantity = (int)Arr::get($field, 'settings.single_inventory_stock');
        $itemName = Arr::get($field, 'settings.label');
        $parentName = Arr::get($field, 'attributes.name');
        $itemPrice = \FluentFormPro\Payments\PaymentHelper::convertToCents(Arr::get($field, 'attributes.value'));
        $usedQuantity = InventoryValidation::getPaymentItemSubmissionQuantity($form->id, $parentName, $itemName,
            $itemPrice);
        $remaining = max($availableQuantity - $usedQuantity, 0);
        $field['settings']['label'] .= str_replace('{remaining_quantity}', $remaining, $stockLabel);
        
        $hideStockoutInput = Arr::get($field, 'settings.hide_input_when_stockout') == 'yes';
        if ($hideStockoutInput && $remaining == 0) {
            $field['settings']['container_class'] .= 'has-conditions ff_excluded ';
        }
        return $field;
    }
    
    public function processInventoryFields($field, $form, $previousSubmissionData)
    {
        $inputType = Arr::get($field, 'attributes.type') ? Arr::get($field, 'attributes.type') : Arr::get($field, 'element');
        $showStock = Arr::get($field, 'settings.show_stock') == 'yes';
        $stockLabel = wp_kses_post(Arr::get($field, 'settings.stock_quantity_label'));
        $hideChoice = Arr::get($field, 'settings.hide_choice_when_stockout') == 'yes';
        
        if ($inputType == 'single') {
            $field = $this->adjustSinglePaymentItem($field, $form, $stockLabel);
        } elseif ($inputType == 'radio' || $inputType == 'checkbox' || $inputType == 'select') {
            $attr = [
                'formId'                 => $form->id,
                'field'                  => $field,
                'previousSubmissionData' => $previousSubmissionData,
                'stockLabel'             => $stockLabel,
                'showStock'              => $showStock,
                'hideChoice'             => $hideChoice,
            ];
            $field = $this->adjustOptions($attr);
        }
    
        $field = apply_filters_deprecated(
            'fluentform_inventory_fields_before_render',
            [
                $field,
                $form,
                $previousSubmissionData
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/inventory_fields_before_render',
            'Use fluentform/inventory_fields_before_render instead of fluentform_survey_shortcode_defaults'
        );
        return apply_filters('fluentform/inventory_fields_before_render', $field, $form, $previousSubmissionData);
    }
    
    /**
     * Get Inventory Settings Activated Fields
     * @param $form
     * @param $type simple|advance
     * @return array
     */
    public static function getInventoryFields($form, $type = 'simple' )
    {
        $inventoryAllowedInputs = InventorySettingsManager::getInventoryInputs();
        $inventoryFields = FormFieldsParser::getElement($form, $inventoryAllowedInputs, ['element', 'attributes', 'settings','label']);
        
        $inventoryActivatedFields = [];
        foreach ($inventoryFields as $fieldName => $field) {
            if (Arr::get($field, 'settings.inventory_type') == $type) {
                $inventoryActivatedFields[$fieldName] = $field;
            }
        }
        return $inventoryActivatedFields;
    }
    
    /**
     * Show or Hide Remaining Inventory Options Comparing Previous Submissions
     * @return void
     * @throws \Exception
     */
    public function processBeforeFormRender()
    {
        static $previousSubmissionCache = [];
        
        add_filter('fluentform/rendering_form', function ($form) use ($previousSubmissionCache) {
            $inventoryFields = static::getInventoryFields($form);
            if (empty($inventoryFields)) {
                return $form;
            }
            if (!isset($previousSubmissionCache[$form->id])) {
                $inventoryFieldsNames = array_keys($inventoryFields);
                $previousSubmissionCache[$form->id] = ReportHelper::getInputReport($form->id,
                    $inventoryFieldsNames, false);
            }
            foreach ($inventoryFields as $inventoryField) {
                $element = $inventoryField['element'];
                add_filter('fluentform/rendering_field_data_' . $element,
                    function ($field, $form) use ($previousSubmissionCache, $inventoryField) {
                        if (Arr::get($inventoryField, 'attributes.name') == Arr::get($field, 'attributes.name')) {
                            $field = $this->processInventoryFields($field, $form, $previousSubmissionCache[$form->id]);
                        }
                        return $field;
                    }, 10, 2);
            }
            
            return $form;
        });
    }
}
