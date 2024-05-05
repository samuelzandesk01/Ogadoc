<?php

namespace FluentFormPro\classes\Inventory;

use FluentForm\Framework\Helpers\ArrayHelper as Arr;

/**
 *  Inventory Module Manager
 *
 * @since 4.3.13
 */
class InventorySettingsManager
{
    
    public static function boot()
    {
        return new self();
    }
    
    public function __construct()
    {
        $this->insertDefaultValues();
        
        $this->insertDefaultValuesToExistingForm();
        
        $this->insertEditorSettings();
        
        /**
         * Process Inventory Fields Options Comparing Previous Submissions
         */
        (new InventoryFieldsRenderer())->processBeforeFormRender();
        
        /**
         * Validate Inventory Form Fields
         */
        add_action('fluentform/before_insert_submission', function ($insertData, $formData, $form) {
            (new InventoryValidation($formData, $form))->validate();
        }, 10, 3);
    }
    
    public function insertDefaultValues()
    {
        $upgradableInputs = static::getInventoryInputs();
        add_filter('fluentform/editor_components', function ($components) use ($upgradableInputs) {
            //mapping select,input_radio,input_checkbox, multi_payment_component keys and groups
            $inventoryFieldsKeyGroupMaps = [
                8  => 'general',
                9  => 'general',
                10 => 'general',
                0  => 'payments',
            ];
            foreach ($inventoryFieldsKeyGroupMaps as $key => $group) {
                $item = Arr::get($components, $group . '.' . $key);
                if (in_array(Arr::get($item, 'element'), $upgradableInputs)) {
                    $inventorySettings = [
                        'inventory_type'             => false,
                        'inventory_stockout_message' => __('This Item is Stock Out', 'fluentformpro'),
                        'hide_choice_when_stockout'  => 'no',
                        'hide_input_when_stockout'   => 'no',
                        'disable_input_when_stockout'   => 'no',
                        'show_stock'                 => 'no',
                        'multiple_inventory_stock'   => '',
                        'single_inventory_stock'     => 10,
                        'stock_quantity_label'       => sprintf(__(' - %s available', 'fluentformpro'),
                            '{remaining_quantity}')
                    ];

                    if (isset($components[$group][$key]['settings'])) {
                        $components[$group][$key]['settings'] = array_merge($components[$group][$key]['settings'], $inventorySettings);
                    }
                }
            }
            return $components;
        }, 10, 1);
    }
    
    public function insertEditorSettings()
    {
        add_filter('fluentform/editor_element_settings_placement', function ($placements) {
            $upgradableInputs = static::getInventoryInputs();
            
            foreach ($upgradableInputs as $inputKey) {
                if (!isset($placements[$inputKey])) {
                    break;
                }
                $placements[$inputKey]['advanced'][] = [
                    array_keys($this->getAdditionalSettings())
                ];
                $placements[$inputKey]['advancedExtras'] = $this->getAdditionalSettings();
            }
            return $placements;
        });
    }
    
    public function getAdditionalSettings()
    {
        return [
            
            'inventory_type'             => [
                'template' => 'radio',
                'label'    => __('Inventory Settings', 'fluentformpro'),
                'options'  => [
                    [
                        'value' => false,
                        'label' => __('Disable', 'fluentformpro'),
                    ],
                    [
                        'value' => 'simple',
                        'label' => __('Enable', 'fluentformpro'),
                    ],
//              todo add shared & scoped inventory
//                    [
//                        'value' => 'advanced',
//                        'label' => __('Advanced', 'fluentformpro'),
//                    ],
                ],
            ],
            'single_inventory_stock'     => [
                'template'   => 'inputNumber',
                'label'      => __('Inventory Quantity', 'fluentformpro'),
                'dependency' => [
                    'depends_on' => 'attributes/type',
                    'value'      => 'single',
                    'operator'   => '=='
                ],
            ],
            'multiple_inventory_stock'   => [
                'template'   => 'inventoryStock',
                'label'      => __('Stock Quantity', 'fluentformpro'),
                'dependency' => [
                    'depends_on' => 'attributes/type',
                    'value'      => 'single',
                    'operator'   => '!='
                ],
            ],
            'inventory_stockout_message' => [
                'template'   => 'inputText',
                'label'      => __('Stock Out Message', 'fluentformpro'),
                'dependency' => [
                    'depends_on' => 'settings/inventory_type',
                    'value'      => false,
                    'operator'   => '!='
                ]
            ],
            'hide_choice_when_stockout'  => [
                'template'   => 'inputYesNoCheckBox',
                'label'      => __('Hide Choice When Stock is Out', 'fluentformpro'),
                'dependency' => [
                    'depends_on' => 'settings/inventory_type',
                    'value'      => false,
                    'operator'   => '!='
                ]
            ],
            'hide_input_when_stockout'   => [
                'template'   => 'inputYesNoCheckBox',
                'label'      => __('Hide Input When Stock is Out', 'fluentformpro'),
                'dependency' => [
                    'depends_on' => 'settings/inventory_type',
                    'value'      => false,
                    'operator'   => '!='
                ]
            ],
            'disable_input_when_stockout'   => [
                'template'   => 'inputYesNoCheckBox',
                'label'      => __('Disable Input When Stock is Out', 'fluentformpro'),
                'dependency' => [
                    'depends_on' => 'settings/inventory_type',
                    'value'      => false,
                    'operator'   => '!='
                ]
            ],
            'show_stock'                 => [
                'template'   => 'inputYesNoCheckBox',
                'label'      => __('Show Available Stock', 'fluentformpro'),
                'dependency' => [
                    'depends_on' => 'settings/inventory_type',
                    'value'      => false,
                    'operator'   => '!='
                ]
            ],
            'stock_quantity_label'       => [
                'template'         => 'inputText',
                'label'            => __('Inventory Label', 'fluentformpro'),
                'inline_help_text' => __('This Label will be appended to the field’s label or field’s Options',
                    'fluentformpro'),
                'dependency'       => [
                    'depends_on' => 'settings/inventory_type',
                    'value'      => false,
                    'operator'   => '!='
                ]
            ],
        ];
    }
    
    public static function getInventoryInputs()
    {
        $fields = [
            'select',
            'input_radio',
            'input_checkbox',
            'multi_payment_component'
        ];
        $fields = apply_filters_deprecated(
            'fluentform_inventory_inputs',
            [
                $fields
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/inventory_inputs',
            'Use fluentform/inventory_inputs instead of fluentform_inventory_inputs.'
        );
        return apply_filters('fluentform/inventory_inputs', $fields);
    }
    
    protected function insertDefaultValuesToExistingForm()
    {
        $upgradableInputs = static::getInventoryInputs();
        foreach ($upgradableInputs as $inputKey) {
            add_filter('fluentform/editor_init_element_' . $inputKey, function ($field) {
                $defaultSettings = [
                    'inventory_stockout_message'  => __('This Item is Stock Out', 'fluentformpro'),
                    'inventory_type'              => '',
                    'hide_choice_when_stockout'   => '',
                    'disable_input_when_stockout' => '',
                    'hide_input_when_stockout'    => '',
                    'show_stock'                  => '',
                    'multiple_inventory_stock'    => '',
                    'single_inventory_stock'      => 1,
                    'stock_quantity_label'        => sprintf(__(' - %s available', 'fluentformpro'), '{remaining_quantity}')
                ];
    
                foreach ($defaultSettings as $settingKey => $defaultValue) {
                    if (!isset($field['settings'][$settingKey])) {
                        $field['settings'][$settingKey] = $defaultValue;
                    }
                }
    
                return $field;
            });
        }
    }
    
}
