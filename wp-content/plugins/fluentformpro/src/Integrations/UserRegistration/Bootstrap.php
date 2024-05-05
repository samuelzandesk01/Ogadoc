<?php

namespace FluentFormPro\Integrations\UserRegistration;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Helpers\Helper;
use FluentForm\App\Http\Controllers\IntegrationManagerController;
use FluentForm\App\Services\ConditionAssesor;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper;

class Bootstrap extends IntegrationManagerController
{
    use Getter;

    public $category = 'wp_core';
    public $disableGlobalSettings = 'yes';
    protected $updateApi;
    protected $regApi;

    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            'User Registration or Update',
            'UserRegistration',
            '_fluentform_user_registration_settings',
            'user_registration_feeds',
            1
        );

        $this->regApi = new UserRegistrationApi();
        $this->updateApi = new UserUpdateFormHandler();

        $this->logo = fluentFormMix('img/integrations/user_registration.png');

        $this->description = 'Create WordPress user or update user when a form is submitted.';

        $this->registerAdminHooks();

        $this->registerHooks();
    }

    protected function registerHooks()
    {
        $isEnabled =  $this->isEnabled();
        if(!$isEnabled){
            return;
        }

        add_filter('fluentform/notifying_async_UserRegistration', '__return_false');

        add_filter('fluentform/save_integration_value_' . $this->integrationKey, [$this, 'handleValidate'], 10, 3);
        add_filter('fluentform/validation_user_registration_errors', [$this->regApi, 'validateSubmittedForm'], 10, 3);
        
        add_filter('fluentform/validation_user_update_errors', [$this->updateApi, 'validateSubmittedForm'], 10, 3);
        add_filter('fluentform/rendering_form', [
            $this->updateApi, 'maybePopulateUserUpdateForm'
        ], 10, 3);

        add_filter(
            'fluentform/get_integration_values_' . $this->integrationKey,
            [$this, 'resolveIntegrationSettings'],
            100,
            3
        );
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'category'                => 'wp_core',
            'disable_global_settings' => 'yes',
            'logo'                    => $this->logo,
            'title'                   => $this->title . ' Integration',
            'is_active'               => $this->isConfigured()
        ];

        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId = null)
    {
        $listId = $this->app->request->get('serviceId', 'user_registration');

        if ($listId == 'user_registration') {
            $name = $this->app->request->get('serviceName', 'User Registration');
            $this->title = 'User Registration';
        } else {
            $name = $this->app->request->get('serviceName', 'User Update');
            $this->title = 'User Update';
        }

        $fields = [
            'name'                 => $name,
            'list_id'                 => $listId,
            'Email'                => '',
            'username'             => '',
            'CustomFields'         => (object)[],
            'userRole'             => 'subscriber',
            'userMeta'             => [
                [
                    'label' => '', 'item_value' => ''
                ]
            ],
            'enableAutoLogin'      => false,
            'sendEmailToNewUser'   => false,
            'validateForUserEmail' => true,
            'conditionals'         => [
                'conditions' => [],
                'status'     => false,
                'type'       => 'all'
            ],
            'enabled'              => true
        ];
    
        $fields = apply_filters_deprecated(
            'fluentform_user_registration_field_defaults',
            [
                $fields,
                $formId
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/user_registration_field_defaults',
            'Use fluentform/user_registration_field_defaults instead of fluentform_user_registration_field_defaults.'
        );

        return apply_filters('fluentform/user_registration_field_defaults', $fields, $formId);
    }

    public function getSettingsFields($settings, $formId = null)
    {
        $fieldSettings = [
            'fields' => [
                [
                    'key'         => 'name',
                    'label'       => 'Name',
                    'required'    => true,
                    'placeholder' => 'Your Feed Name',
                    'component'   => 'text'
                ],
                [
                    'key' => 'list_id',
                    'label' => 'Services',
                    'placeholder' => 'Choose Service',
                    'required' => true,
                    'component' => 'refresh',
                    'options' => [
                        'user_registration' => 'User Registration',
                        'user_update' => 'User Update',
                    ]
                ],
            ],
            'button_require_list' => false,
            'integration_title' => $this->title
        ];

        $listId = $this->app->request->get('serviceId', ArrayHelper::get($settings, 'list_id'));
        $inline_msg ='';
        if ($listId) {
            $fields = $this->getFields($listId);
            $fields = array_merge($fieldSettings['fields'], $fields);
            $fieldSettings['fields'] = $fields;

            if ($listId == 'user_update') {
                $this->title = 'User Update';
                $inline_msg = 'Please note that, This action will only run if the user is logged in.';
            } else {
                $this->title = 'User Registration';
            }
        }
        $fieldSettings['fields'] = array_merge($fieldSettings['fields'], [
            [
                'require_list' => false,
                'key'          => 'conditionals',
                'label'        => 'Conditional Logics',
                'tips'         => 'Allow '. $this->title . ' integration conditionally based on your submission values',
                'component'    => 'conditional_block'
            ],
            [
                'require_list'   => false,
                'key'            => 'enabled',
                'label'          => 'Status',
                'component'      => 'checkbox-single',
                'checkbox_label' => 'Enable This feed',
                'inline_tip'     => $inline_msg ?: 'Please note that, This action will only run if the visitor is logged out state and the email is not registered yet'
            ]
        ]);

        if ($listId && $listId == 'user_registration') {
            $fieldSettings['fields'] = apply_filters_deprecated(
                'fluentform_user_registration_feed_fields',
                [
                    $fieldSettings['fields'],
                    $formId
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/user_registration_feed_fields',
                'Use fluentform/user_registration_feed_fields instead of fluentform_user_registration_feed_fields.'
            );
            $fieldSettings['fields'] = apply_filters('fluentform/user_registration_feed_fields', $fieldSettings['fields'], $formId);
        }
        if ($listId && $listId == 'user_update') {
            $fieldSettings['fields'] = apply_filters_deprecated(
                'fluentform_user_update_feed_fields',
                [
                    $fieldSettings['fields'],
                    $formId
                ],
                FLUENTFORM_FRAMEWORK_UPGRADE,
                'fluentform/user_update_feed_fields',
                'Use fluentform/user_update_feed_fields instead of fluentform_user_update_feed_fields.'
            );
            $fieldSettings['fields'] = apply_filters('fluentform/user_update_feed_fields', $fieldSettings['fields'], $formId);
        }

        return $fieldSettings;
    }

    public function handleValidate($settings, $integrationId, $formId)
    {
        $parseSettings = $this->validate(
            $settings, $this->getSettingsFields($settings)
        );

        if (ArrayHelper::get($parseSettings, 'list_id') == 'user_update') {
            Helper::setFormMeta($formId, '_has_user_update', 'yes');
        } else {
            Helper::setFormMeta($formId, '_has_user_registration', 'yes');
        }

        return $parseSettings;
    }

    

    /*
     * Form Submission Hooks Here
     */
    public function notify($feed, $formData, $entry, $form)
    {
        $feedValue = ArrayHelper::get($feed,'processedValues');
        $this->resolveCustomMetaArrayValue($feed, $formData);

        if (
            $feedValue &&
            ArrayHelper::get($feedValue, 'list_id') &&
            ArrayHelper::get($feedValue, 'list_id') === 'user_update'
        ) {
            $this->updateApi->handleUpdateUser($feed, $formData, $entry, $form, $this->integrationKey);
        } else {
            $this->regApi->registerUser(
                $feed, $formData, $entry, $form, $this->integrationKey
            );
        }
    }

    // There is no global settings, so we need
    // to return true to make this module work.
    public function isConfigured()
    {
        return true;
    }

    // This is an absttract method, so it's required.
    public function getMergeFields($list, $listId, $formId)
    {
        // ...
    }

    // This method should return global settings. It's not required for
    // this class. So we should return the default settings otherwise
    // there will be an empty global settings page for this module.
    public function addGlobalMenu($setting)
    {
        return $setting;
    }



    protected function getFields($listId)
    {
        if ($listId == 'user_update') {
            $map_primary_field = $this->updateApi->userUpdateMapFields();
        } else {
            $map_primary_field = $this->regApi->userRegistrationMapFields();
        }

        $customFieldMsg = $listId == 'user_update' ? 'update' : 'registration';
        $fields = [
            [
                'key'                => 'CustomFields',
                'require_list'       => false,
                'label'              => 'Map Fields',
                'tips'               => 'Associate your user '. $customFieldMsg .' fields to the appropriate Fluent Forms fields by selecting the appropriate form field from the list.',
                'component'          => 'map_fields',
                'field_label_remote' => 'User ' . ucfirst($customFieldMsg) . ' Field',
                'field_label_local'  => 'Form Field',
                'primary_fileds'     => $map_primary_field
            ],
        ];
        if ($listId == 'user_registration') {
            $fields[] = [
                'require_list' => false,
                'required' => true,
                'key' => 'userRole',
                'label' => 'Default User Role',
                'tips' => 'Set default user role when registering a new user.',
                'component' => 'radio_choice',
                'options' => $this->regApi->getUserRoles()
            ];
            $fields[] = [
                'require_list' => false,
                'key' => 'userMeta',
                'label' => 'User Meta',
                'tips' => 'Add user meta.',
                'component' => 'dropdown_label_repeater',
                'field_label' => 'User Meta Key',
                'value_label' => 'User Meta Value'
            ];
            $fields[] = [
                'require_list' => false,
                'key' => 'enableAutoLogin',
                'label' => 'Auto Login',
                'checkbox_label' => 'Allow the user login automatically after registration',
                'component' => 'checkbox-single',
            ];
            $fields[] = [
                'require_list' => false,
                'key' => 'sendEmailToNewUser',
                'label' => 'Email Notification',
                'checkbox_label' => 'Send default WordPress welcome email to user after registration',
                'component' => 'checkbox-single',
            ];
            $fields[] = [
                'require_list' => false,
                'key' => 'validateForUserEmail',
                'label' => 'Form Validation',
                'checkbox_label' => 'Do not submit the form if user already exist in Database',
                'component' => 'checkbox-single',
            ];
        } else {
            $fields[] = [
                'require_list' => false,
                'key' => 'userMeta',
                'label' => 'User Meta',
                'tips' => 'Add user meta.',
                'component' => 'dropdown_label_repeater',
                'field_label' => 'User Meta Key',
                'value_label' => 'User Meta Value'
            ];
        }
        return $fields;
    }

    private function resolveCustomMetaArrayValue(&$feed, $formData)
    {
        if (!ArrayHelper::get($feed, 'processedValues.userMeta', [])) {
          return;
        }
        $userMetaMapping = ArrayHelper::get($feed, 'settings.userMeta', []);
        foreach ($feed['processedValues']['userMeta'] as $index => &$userMeta) {
            if (
                $userMeta['label'] === ArrayHelper::get($userMetaMapping, $index . '.label', '') &&
                $inputName = Helper::getInputNameFromShortCode(ArrayHelper::get($userMetaMapping, $index . '.item_value', ''))
            ) {
                if (isset($formData[$inputName]) && is_array($formData[$inputName])) {
                    $userMeta['item_value'] = $formData[$inputName];
                }
            }

        }
    }

    public function resolveIntegrationSettings($settings, $feed, $formId)
    {

        $serviceId = $this->app->request->get('serviceId', '');
        $serviceName = $this->app->request->get('serviceName', '');

        if ($serviceName) {
            $settings['name'] = $serviceName;
        }

        if ($serviceId) {
            $settings['list_id'] = $serviceId;
        }

        if ( $serviceId = ArrayHelper::get($settings,'list_id')) {
            if ($serviceId == 'user_update') {
                $this->title = 'User Update';
            } else {
                $this->title = 'User Registration';
            }
        } else {
            $this->title = 'User Registration';
        }

        return $settings;
    }

}
