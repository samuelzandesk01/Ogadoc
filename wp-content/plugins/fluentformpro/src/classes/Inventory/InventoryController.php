<?php

namespace FluentFormPro\classes\Inventory;

use FluentForm\Framework\Helpers\ArrayHelper;

/**
 *  Handling Inventory Global Module.
 *
 * @since 4.3.13
 */
class InventoryController
{
    protected $key = 'inventory_module';
    
    public function boot()
    {
        $enabled = $this->isEnabled();
        
        add_filter('fluentform/global_addons', function ($addOns) use ($enabled) {
            $addOns[$this->key] = [
                'title'       => 'Inventory Module',
                'description' => __('Powerful Inventory Management. Manage resources for events booking, reservations, or for selling products and tickets!',
                    'fluentformpro'),
                'logo'        => fluentFormMix('img/integrations/inventory.png'),
                'enabled'     => ($enabled) ? 'yes' : 'no',
                'config_url'  => '',
                'category'    => '' //Category : All
            ];
            return $addOns;
        }, 9);
        
        if (!$enabled) {
            return;
        }
        InventorySettingsManager::boot();
        InventoryList::boot();
    }
    
    public function isEnabled()
    {
        $globalModules = get_option('fluentform_global_modules_status');
        $inventoryModule = ArrayHelper::get($globalModules, $this->key);
        if ($inventoryModule == 'yes') {
            return true;
        }
        return false;
    }
    
}
