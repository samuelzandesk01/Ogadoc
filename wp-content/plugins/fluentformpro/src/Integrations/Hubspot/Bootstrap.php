<?php

namespace FluentFormPro\Integrations\Hubspot;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper;

class Bootstrap extends IntegrationManager
{
    public function __construct(Application $app)
    {
        parent::__construct(
            $app,
            'HubSpot',
            'hubspot',
            '_fluentform_hubspot_settings',
            'hubspot_feed',
            26
        );

        $this->logo = fluentFormMix('img/integrations/hubspot.png');

//         add_filter('fluentform/notifying_async_hubspot', '__return_false');

        $this->description = 'Connect HubSpot with Fluent Forms and subscribe a contact when a form is submitted.';

        $this->registerAdminHooks();
    }

    public function getGlobalFields($fields)
    {
        return [
            'logo' => $this->logo,
            'menu_title' => __('Hubspot API Settings', 'fluentformpro'),
            'menu_description' => __('Hubspot is a CRM software. Use Fluent Forms to collect customer information and automatically add to Hubspot. Please login to your Hubspot account and Create a new private app with scope <b>crm.schemas.contacts.read</b> and copy your token, check this <a href="https://wpmanageninja.com/docs/fluent-form/integrations-available-in-wp-fluent-form/hubspot-integration-with-wp-fluent-form-wordpress-plugin/" target="_blank">link</a> for details. If you have pro version also check <b>contacts-lists-access</b> scope', 'fluentformpro'),
            'valid_message' => __('Your Hubspot access token is valid', 'fluentformpro'),
            'invalid_message' => __('Your Hubspot access token is not valid', 'fluentformpro'),
            'save_button_text' => __('Save Settings', 'fluentformpro'),
            'fields' => [
                'accessToken' => [
                    'type' => 'password',
                    'placeholder' => 'Access Token',
                    'label_tips' => __("Enter your Hubspot access Token, if you do not have <br>Please login to your Hubspot account and<br>Create a new private app with scope <b>contacts-lists-access</b> and copy your token", 'fluentformpro'),
                    'label' => __('Hubspot Access Token', 'fluentformpro'),
                ]
            ],
            'hide_on_valid' => true,
            'discard_settings' => [
                'section_description' => 'Your HubSpot API integration is up and running',
                'button_text' => 'Disconnect HubSpot',
                'data' => [
                    'accessToken' => ''
                ]
            ]
        ];
    }

    public function getGlobalSettings($settings)
    {
        $globalSettings = get_option($this->optionKey);
        if (!$globalSettings) {
            $globalSettings = [];
        }
        $defaults = [
            'accessToken' => '',
            'apiKey' => '',
            'status' => ''
        ];

        return wp_parse_args($globalSettings, $defaults);
    }

    public function saveGlobalSettings($settings)
    {
        if (!$settings['accessToken']) {
            $integrationSettings = [
                'accessToken' => '',
                'apiKey' => '',
                'status' => false
            ];
            update_option($this->optionKey, $integrationSettings, 'no');

            wp_send_json_success([
                'message' => __('Your settings has been updated and discarded', 'fluentformpro'),
                'status' => false
            ], 200);
        }

        // Verify API key now
        try {
            $integrationSettings = [
                'accessToken' => sanitize_text_field($settings['accessToken']),
                'apiKey'      => '',
                'status'      => false
            ];
            update_option($this->optionKey, $integrationSettings, 'no');

            $api = new API($settings['apiKey'],$settings['accessToken']);
            $result = $api->auth_test();

            if (is_wp_error($result)) {
                throw new \Exception($result->get_error_message());
            }

            if (!empty($result['message'])) {
                throw new \Exception($result['message']);
            }

        } catch (\Exception $exception) {
            wp_send_json_error([
                'message' => $exception->getMessage()
            ], 400);
        }

        // Integration key is verified now, Proceed now

        $integrationSettings = [
            'accessToken' => sanitize_text_field($settings['accessToken']),
            'apiKey' => '',
            'status' => true
        ];

        // Update the reCaptcha details with siteKey & secretKey.
        update_option($this->optionKey, $integrationSettings, 'no');

        wp_send_json_success([
            'message' => __('Your HubSport API  has been verified and successfully set', 'fluentformpro'),
            'status' => true
        ], 200);
    }

    public function pushIntegration($integrations, $formId)
    {
        $integrations[$this->integrationKey] = [
            'title' => $this->title . ' Integration',
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => 'Configuration required!',
            'global_configure_url' => admin_url('admin.php?page=fluent_forms_settings#general-hubspot-settings'),
            'configure_message' => 'HubSpot is not configured yet! Please configure your HubSpot api first',
            'configure_button_text' => 'Set HubSpot API'
        ];
        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId)
    {
        return [
            'name' => '',
            'list_id' => '',
            'email' => '',
            'firstname' => '',
            'lastname' => '',
            'website' => '',
            'company' => '',
            'phone' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'fields' => (object)[],
            'other_fields_mapping' => [
	            [
		            'item_value' => '',
		            'label' => ''
	            ]
            ],
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'contact_update' => false,
            'enabled' => true
        ];
    }

    public function getSettingsFields($settings, $formId)
    {
        return [
            'fields' => [
                [
                    'key' => 'name',
                    'label' => 'Name',
                    'required' => true,
                    'placeholder' => 'Your Feed Name',
                    'component' => 'text'
                ],
                [
                    'key' => 'list_id',
                    'label' => 'HubSpot List(HubSpot Pro)',
                    'placeholder' => 'Select HubSpot Mailing List',
                    'tips' => 'HubSpot just restricted this for Pro Users. Select the HubSpot Mailing List you would like to add your contacts to.',
                    'component' => 'list_ajax_options',
                    'options' => $this->getLists(),
                ],
                [
                    'key' => 'fields',
                    'require_list' => false,
                    'label' => 'Map Fields',
                    'tips' => 'Select which Fluent Forms fields pair with their<br /> respective HubSpot fields.',
                    'component' => 'map_fields',
                    'field_label_remote' => 'HubSpot Field',
                    'field_label_local' => 'Form Field',
                    'primary_fileds' => [
                        [
                            'key' => 'email',
                            'label' => 'Email Address',
                            'required' => true,
                            'input_options' => 'emails'
                        ],
                        [
                            'key' => 'firstname',
                            'label' => 'First Name'
                        ],
                        [
                            'key' => 'lastname',
                            'label' => 'Last Name'
                        ],
                        [
                            'key' => 'website',
                            'label' => 'Website'
                        ],
                        [
                            'key' => 'company',
                            'label' => 'Company'
                        ],
                        [
                            'key' => 'phone',
                            'label' => 'Phone'
                        ],
                        [
                            'key' => 'address',
                            'label' => 'Address'
                        ],
                        [
                            'key' => 'city',
                            'label' => 'City'
                        ],
                        [
                            'key' => 'state',
                            'label' => 'State'
                        ],
                        [
                            'key' => 'zip',
                            'label' => 'Zip'
                        ],
                    ]
                ],
	            [
		            'key'                => 'other_fields_mapping',
		            'require_list'       => false,
		            'label'              => 'Other Fields',
		            'tips'               => 'Select which Fluent Forms fields pair with their<br /> respective HubSpot fields.',
		            'component'          => 'dropdown_many_fields',
		            'field_label_remote' => 'HubSpot Field',
		            'field_label_local'  => 'Form Field',
		            'options'            => $this->getOtherFields()
	            ],
                [
                    'require_list' => false,
                    'key' => 'conditionals',
                    'label' => 'Conditional Logics',
                    'tips' => 'Allow HubSpot integration conditionally based on your submission values',
                    'component' => 'conditional_block'
                ],
                [
                    'require_list' => false,
                    'key' => 'contact_update',
                    'label' => 'Update',
                    'component' => 'checkbox-single',
                    'checkbox_label' => 'Enable Contact Update'
                ],
                [
                    'require_list' => false,
                    'key' => 'enabled',
                    'label' => 'Status',
                    'component' => 'checkbox-single',
                    'checkbox_label' => 'Enable This feed'
                ]
            ],
            'button_require_list' => false,
            'integration_title' => $this->title
        ];
    }

    protected function getLists()
    {
        $api = $this->getRemoteClient();
        $lists = $api->getLists();
        $formattedLists = [];
        foreach ($lists as $list) {
            $formattedLists[$list['listId']] = $list['name'];
        }
        return $formattedLists;
    }

    public function getMergeFields($list, $listId, $formId)
    {
        return [];
    }

    public function getRemoteClient()
    {
        $settings = $this->getGlobalSettings([]);
        return new API($settings['apiKey'],$settings['accessToken']);
    }

	public function getOtherFields()
	{
		$api = $this->getRemoteClient();
		$fields = $api->getAllFields();
        $customFormattedFields = [];

        $customFields = $api->getCustomFields();
        foreach ($customFields as $customField) {
            $customFormattedFields[$customField['name']] = $customField['label'];
        }

		$formattedFields = [];

		foreach ($fields as $field) {
			$formattedFields[$field['name']] = $field['label'];
		}

		$formattedFields = ArrayHelper::except($formattedFields, [
			'email',
			'firstname',
			'lastname',
			'website',
			'company',
			'phone',
			'address',
			'city',
			'state',
			'zip'
		]);

        $formattedFields = array_merge($formattedFields, $customFormattedFields);

		return $formattedFields;
	}

    private function isDate($value)
    {
        if (!$value) {
            return false;
        }

        try {
            new \DateTime($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /*
     * Notification Handler
     */

    public function notify($feed, $formData, $entry, $form)
    {
        $feedData = $feed['processedValues'];
        if (!is_email($feedData['email'])) {
            $feedData['email'] = ArrayHelper::get($formData, $feedData['email']);
        }
        if (!is_email($feedData['email'])) {
            do_action('fluentform/integration_action_result', $feed, 'failed', 'Hubspot API call has been skipped because no valid email available');
        }

        $mainFields = ArrayHelper::only($feedData, [
            'email',
            'firstname',
            'lastname',
            'website',
            'company',
            'phone',
            'address',
            'city',
            'state',
            'zip'
        ]);

        $fields = array_filter(array_merge($mainFields, ArrayHelper::get($feedData, 'fields', [])));

        if(!empty($feedData['other_fields_mapping'])) {
            foreach ($feedData['other_fields_mapping'] as $field) {
                if (!empty($field['item_value'])) {
                    $fields[$field['label']] = $field['item_value'];
                    $dateField = $this->isDate($field['item_value']);
                    if ($dateField) {
                        $fields[$field['label']] = strtotime($field['item_value'])*1000;
                    }
                }
            }
        }
    
        $fields = apply_filters_deprecated(
            'fluentform_hubspot_field_data',
            [
                $fields,
                $feed,
                $entry,
                $form
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/hubspot_field_data',
            'Use fluentform/hubspot_field_data instead of fluentform_hubspot_field_data'
        );

        $fields = apply_filters('fluentform/hubspot_field_data', $fields, $feed, $entry, $form);
    
        $fields = apply_filters_deprecated(
            'fluentform_integration_data_' . $this->integrationKey,
            [
                $fields,
                $feed,
                $entry
            ],
            FLUENTFORM_FRAMEWORK_UPGRADE,
            'fluentform/integration_data_' . $this->integrationKey,
            'Use fluentform/integration_data_' . $this->integrationKey . ' instead of fluentform_integration_data_' . $this->integrationKey
        );

        $fields = apply_filters('fluentform/integration_data_' . $this->integrationKey, $fields, $feed, $entry);


        // Now let's prepare the data and push to hubspot
        $api = $this->getRemoteClient();
        $updateContact =  ArrayHelper::get ($feedData,'contact_update');
        $response = $api->subscribe($feedData['list_id'], $fields , $updateContact);

        if (is_wp_error($response)) {
            do_action('fluentform/integration_action_result', $feed, 'failed', $response->get_error_message());
        } else {
            do_action('fluentform/integration_action_result', $feed, 'success', 'Hubspot feed has been successfully initialed and pushed data');
        }
    }

}
