<?php

namespace FluentFormPro\classes\Inventory;

use FluentForm\App\Modules\Form\FormFieldsParser;
use FluentForm\App\Services\Report\ReportHelper;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;

/**
 *  Inventory Fields Validation
 *
 * @since 4.3.13
 */
class InventoryValidation
{
    protected $form;
    protected $formData;
    private $quantityItems;
    
    
    public function __construct($formData, $form)
    {
        $this->formData = $formData;
        $this->form = $form;
        $this->maybeSetItemQuantity();
    }
    
    private function maybeSetItemQuantity()
    {
        $quantityItems = [];
        $quantityFields = FormFieldsParser::getElement($this->form, ['item_quantity_component'],
            ['attributes', 'settings']);
        if (!empty($quantityFields)) {
            foreach ($quantityFields as $field) {
                $quantityItems[Arr::get($field, 'settings.target_product')] = Arr::get($field, 'attributes.name');
            }
        }
        $this->quantityItems = $quantityItems;
    }
    
    /**
     * Validates Inventory Items
     */
    public function validate()
    {
        $inventoryFields = InventoryFieldsRenderer::getInventoryFields($this->form);
        if (empty($inventoryFields)) {
            return;
        }
        $prevSubmissions = ReportHelper::getInputReport($this->form->id, array_keys($inventoryFields), false);
        $errors = [];
        foreach ($inventoryFields as $fieldName => $item) {
            $stockOutMsg = sanitize_text_field(Arr::get($item, 'settings.inventory_stockout_message'));
            $isPaymentInput = Arr::get($item, 'settings.is_payment_field') == 'yes';
            $inputType = Arr::get($item, 'attributes.type') ? Arr::get($item, 'attributes.type') : Arr::get($item,
                'element');
            try {
                $this->isEmpty($fieldName);
            
                if ($inputType == 'single') {
                    $this->handleSinglePaymentInput($fieldName, $item);
                } elseif ($inputType == 'radio' || $inputType == 'select') {
                    $this->handleRadioSelect($fieldName, $item, $prevSubmissions, $isPaymentInput);
                } elseif ($inputType == 'checkbox') {
                    $this->handleCheckbox($fieldName, $item, $prevSubmissions, $isPaymentInput);
                }
            } catch (\Exception $e) {
                if ($e->getMessage() == 'continue') {
                    continue;
                } elseif ($e->getMessage() == 'stock-out') {
                    $stockOutMsg = apply_filters_deprecated(
                        'fluentform_inventory_validation_error',
                        [
                            $stockOutMsg,
                            $fieldName,
                            $item,
                            $this->formData,
                            $this->form
                        ],
                        FLUENTFORM_FRAMEWORK_UPGRADE,
                        'fluentform/inventory_validation_error',
                        'Use fluentform/inventory_validation_error instead of fluentform_inventory_validation_error.'
                    );
                    $errors[$fieldName] = [
                        'stock-out' => wpFluentForm()->applyFilters('fluentform/inventory_validation_error',
                            $stockOutMsg, $fieldName, $item, $this->formData, $this->form)
                    ];
                    break;
                }
            }
        }
        if (!empty($errors)) {
            $app = wpFluentForm();
            $fields = FormFieldsParser::getInputs($this->form, ['rules', 'raw']);
            $errors = apply_filters_deprecated(
                'fluentform_validation_error',
                [
                    $errors,
                    $this->form,
                    $fields,
                    $this->formData
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/validation_error',
                'Use fluentform/validation_error instead of fluentform_validation_error.'
            );
            $errors = $app->applyFilters('fluentform/validation_error', $errors, $this->form, $fields, $this->formData);
            wp_send_json(['errors' => $errors], 423);
        }
    }
    
    private function getQuantity($productName, $formData)
    {
        $quantity = 1;
        if (!$this->quantityItems) {
            return $quantity;
        }
        if (!isset($this->quantityItems[$productName])) {
            return $quantity;
        }
        $inputName = $this->quantityItems[$productName];
        $quantity = Arr::get($formData, $inputName);
        if (!$quantity) {
            return 0;
        }
        return intval($quantity);
    }
    
    public static function getPaymentItemSubmissionQuantity($formId, $parentName, $name, $price)
    {
        static $quantityCache = [];
        if (!isset($quantityCache[$formId])) {
            $quantityCache[$formId] = self::runSumQuantityQuery($formId);
        }
        if (!empty($quantityCache[$formId])) {
            foreach ($quantityCache[$formId] as $qty) {
                if ($qty->item_name == $name && $qty->item_price == $price && $qty->parent_holder == $parentName) {
                    return (int)$qty->total_count;
                }
            }
        }
        return 0;
    }
    
    public static function getItemFromOptionName($item, $key)
    {
        $isPaymentInput = Arr::get($item, 'settings.is_payment_field') == 'yes';
        $options = [];
        if ($isPaymentInput) {
            $options = Arr::get($item, 'settings.pricing_options', []);
        } else {
            $options = Arr::get($item, 'settings.advanced_options', []);
        }
        $selectedOption = [];
        foreach ($options as $option) {
            $label = sanitize_text_field($option['label']);
            $value = sanitize_text_field($option['value']);
            if ($label == $key || $value == $key) {
                $selectedOption = $option;
            }
        }
        if (!$selectedOption || empty($selectedOption['value'])) {
            return false;
        }
        return [
            'parent_input_name' => Arr::get($item, 'attributes.name'),
            'item_name'         => Arr::get($selectedOption, 'label'),
            'item_value'        => Arr::get($selectedOption, 'value'),
            'quantity'          => Arr::get($selectedOption, 'quantity')
        ];
    }
    
    public static function getRegularItemUsedQuantity($previousSubmissionData, $item)
    {
        $name = Arr::get($item, 'parent_input_name');
        $optionName = Arr::get($item, 'item_name');
        $optionValue = Arr::get($item, 'item_value');
        
        $data = Arr::get($previousSubmissionData, $name . '.reports');
        if (!empty($data)) {
            foreach ($data as $datum) {
                if (($datum['value'] == $optionName) || $datum['value'] == $optionValue) {
                    return intval($datum['count']);
                }
            }
        }
        return 0;
    }
    
    private function handleSinglePaymentInput($key, $item)
    {
        $availableQuantity = (int)Arr::get($item, 'settings.single_inventory_stock');
        $selectedQuantity = $this->getQuantity($key, $this->formData);
        if (!$selectedQuantity) {
            throw new \Exception("continue");
        }
        $itemName = Arr::get($item, 'settings.label');
        $parentName = Arr::get($item, 'attributes.name');
    
        $itemPrice = \FluentFormPro\Payments\PaymentHelper::convertToCents(Arr::get($item, 'attributes.value'));
       
        $submissionsSum = $this->getPaymentItemSubmissionQuantity($this->form->id, $parentName, $itemName, $itemPrice) + $selectedQuantity;
        if ($submissionsSum > $availableQuantity) {
            throw new \Exception("stock-out");
        }
    }
   
    private function handleRadioSelect($key, $item, $prevSubmissions, $isPaymentInput)
    {
        $item = $this->getItemFromOptionName($item, $this->formData[$key]);
        
        if ($item) {
            if ($isPaymentInput) {
                $selectedQuantity = $this->getQuantity($item['parent_input_name'], $this->formData);
                if (!$selectedQuantity) {
                    throw new \Exception("continue");
                }
                $itemName = $item['item_name'];
                $itemPrice = \FluentFormPro\Payments\PaymentHelper::convertToCents($item['item_value']);
                
                $submissionsSum = $this->getPaymentItemSubmissionQuantity($this->form->id, $item['parent_input_name'], $itemName, $itemPrice);
            } else {
                $selectedQuantity = 1;
                $submissionsSum = $this->getRegularItemUsedQuantity($prevSubmissions, $item);
            }
            $submissionsSum = $submissionsSum + $selectedQuantity;
            
            if ($submissionsSum > $item['quantity']) {
                throw new \Exception("stock-out");
            }
        }
    }
    
    private function handleCheckbox($key, $item, $prevSubmissions, $isPaymentInput)
    {
        $selectedItems = $this->formData[$key];
        foreach ($selectedItems as $selectedItem) {
    
            $formattedItem = $this->getItemFromOptionName($item, $selectedItem);
            if (!$formattedItem) {
                throw new \Exception("continue");
            }
    
            $selectedQuantity = 1;
            if ($isPaymentInput) {
                $itemName = $formattedItem['item_name'];
                $itemPrice = \FluentFormPro\Payments\PaymentHelper::convertToCents($formattedItem['item_value']);
                $submissionsSum = $this->getPaymentItemSubmissionQuantity($this->form->id, $formattedItem['parent_input_name'], $itemName, $itemPrice);

                $selectedQuantity = $this->getQuantity($formattedItem['parent_input_name'], $this->formData);
                if (!$selectedQuantity) {
                    throw new \Exception("continue");
                }
            } else {
                $submissionsSum = $this->getRegularItemUsedQuantity($prevSubmissions, $formattedItem);
            }
      
            $submissionsSum = $submissionsSum + $selectedQuantity;
            if ($submissionsSum > $formattedItem['quantity']) {
                throw new \Exception("stock-out");
            }
        }
    }
    
    private function isEmpty($key)
    {
        if (!isset($this->formData[$key])) {
            throw new \Exception("continue");
        }
    }
    
    private static function runSumQuantityQuery($formId)
    {
        global  $wpdb;
        $quantity = wpFluent()->table('fluentform_order_items')
            ->select([
                'fluentform_order_items.item_name',
                'fluentform_order_items.item_price',
                'fluentform_order_items.quantity',
                'fluentform_order_items.parent_holder',
                wpFluent()->raw('sum(' . $wpdb->prefix . 'fluentform_order_items.quantity) as total_count')
            ])
            ->where('fluentform_order_items.form_id', $formId)
            ->groupBy('fluentform_order_items.item_name')
            ->groupBy('fluentform_order_items.item_price')
            ->groupBy('fluentform_order_items.parent_holder')
            ->where('fluentform_submissions.payment_status', '!=', 'refunded')
            ->rightJoin('fluentform_submissions', 'fluentform_submissions.id', '=',
                'fluentform_order_items.submission_id')
            ->get();
        return (array)$quantity;
    }
    
}
