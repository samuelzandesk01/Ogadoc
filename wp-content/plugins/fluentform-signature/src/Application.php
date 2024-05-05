<?php

namespace FluentFormSignature;

class Application {
	/**
	 * The fluentform instance.
	 *
	 * @var \FluentForm\Framework\Foundation\Application
	 */
	protected $fluentForm;

	/**
	 * Boot the fluentform signature plugin.
	 */
	public function boot() {
		// If fluentform itself is not installed we'll show admin notice
		// notifying the user that they have to install that first.
		if ( ! defined( 'FLUENTFORM' ) ) {
			 $this->injectDependency();
            return false;
		}

		add_action('fluentform/loaded', function ( $fluentForm ) {
			$this->fluentForm = $fluentForm;

			$this->adminHooks();

			$this->commonHooks();

			$this->publicHooks();
		} );
	}

	/**
	 * Register admin/backend hooks
	 */
	public function adminHooks() {
		$this->fluentForm->addfilter( 'fluentform/editor_components', function ( $components ) {
			return \FluentFormSignature\Component::push( $components );
		} );
		
		add_filter( 'fluentform/editor_element_settings_placement', function ( $placement_settings ) {
			return \FluentFormSignature\Component::placementSettings( $placement_settings );
        } );
		
		add_filter('fluentform/editor_element_search_tags', function ($tags) {
			return \FluentFormSignature\Component::addTags( $tags );
		});
		
		add_filter('fluentform/editor_element_customization_settings', function ($customization_settings) {
			return \FluentFormSignature\Component::customizationSettings( $customization_settings );
		});
    }

	/**
	 * Register common hooks.
	 */
	public function commonHooks() {
		// Prepare the submission data for signature field on Form Submission.
		$this->fluentForm->addfilter( 'fluentform/insert_response_data',
			function ( $formData, $formId, $inputConfigs ) {
				return ( new \FluentFormSignature\Signature )->add( $formData, $formId, $inputConfigs );
			}, 10, 3 );

		// Push the signature field type to the fluentform
		// field types to be available in FormFieldParser.
		$this->fluentForm->addfilter( 'fluentform/form_input_types', function ( $types ) {
			$types[] = 'signature';
			return $types;
		} );

        add_filter('fluentform/response_render_signature', function ($response, $field, $form_id, $isHtml = false) {
            if(!$response) {
                return '';
            }
            return \FluentForm\App\Modules\Form\FormDataParser::formatImageValues([0 => $response], true);
        }, 10, 4);

	}

	/**
	 * Register the public hooks.
	 */
	private function publicHooks() {
		include FLUENTFORM_SIGNATURE_DIR . '/src/Field.php';

		// Render the signature field in the form.
		add_action( 'fluentform/render_item_signature', function ( $signature, $form ) {
			( new \FluentFormSignature\Field )->compile( $signature, $form );
		}, 10, 2 );
	}

	/**
	 * Notify the user about the FluentForm dependency and instructs to install it.
	 */
	private function injectDependency() {
		add_action( 'admin_notices', function () {
			$pluginInfo = $this->getFluentFormInstallationDetails();

			$class = 'notice notice-error';

			$install_url_text = 'Click Here to Install the Plugin';

			if ( $pluginInfo->action == 'activate' ) {
				$install_url_text = 'Click Here to Activate the Plugin';
			}

			$message = 'FluentForm Signature Add-On Requires FluentForm Base Plugin, <b><a href="' . $pluginInfo->url
			           . '">' . $install_url_text . '</a></b>';

			printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), $message );
		} );
	}

	/**
	 * Get the FluentForm plugin installation information e.g. the URL to install.
	 *
	 * @return \stdClass $activation
	 */
	private function getFluentFormInstallationDetails() {
		$activation = (object) [
			'action' => 'install',
			'url'    => ''
		];

		$allPlugins = get_plugins();

		if ( isset( $allPlugins['fluentform/fluentform.php'] ) ) {
			$url = wp_nonce_url(
				self_admin_url( 'plugins.php?action=activate&plugin=fluentform/fluentform.php' ),
				'activate-plugin_fluentform/fluentform.php'
			);

			$activation->action = 'activate';
		} else {
			$api = (object) [
				'slug' => 'fluentform'
			];

			$url = wp_nonce_url(
				self_admin_url( 'update.php?action=install-plugin&plugin=' . $api->slug ),
				'install-plugin_' . $api->slug
			);
		}

		$activation->url = $url;

		return $activation;
	}
}
