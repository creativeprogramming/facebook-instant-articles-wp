<?php
/**
 * Facebook Instant Articles for WP.
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package default
 */

require_once( dirname( __FILE__ ) . '/class-instant-articles-option.php' );

/**
 * Configuration class for Ads.
 */
class Instant_Articles_Option_Ads extends Instant_Articles_Option {

	const OPTION_KEY = IA_PLUGIN_TEXT_DOMAIN . '-option-ads';

	const SECTIONS = array(
		'title' => 'Ads',
		'description' => 'This is where you manage your ads.',
	);

	const FIELDS = array(

		'ad_source' => array(
			'label' => 'Ad Type',
			'description' => 'Instant Articles supports both Audience Network and Direct Sold banner ads',
			'render' => array( 'Instant_Articles_Option_Ads', 'custom_render_ad_source' ),
			'select_options' => array(
				'none' => 'No Ads',
				'fan' => 'Facebook Audience Network',
				'iframe' => 'Custom iframe URL',
				'embed' => 'Custom Embed Code',
			),
			'default' => 'none',
		),

		'fan_placement_id' => array(
			'label' => 'Audience Network Placement ID',
			'description' => 'Find your Placement ID for Facebook Audience Network on your app\'s Audience Network Portal',
			'default' => null,
		),

		'iframe_url' => array(
			'label' => 'URL for iframe',
			'placeholder' => '//ad-server.com/my-ad',
			'default' => '',
		),

		'embed_code' => array(
			'label' => 'Embed Code',
			'render' => 'textarea',
			'default' => '',
			'placeholder' => '<script>...</script>',
		),

		'dimensions' => array(
			'label' => 'Ad Dimensions',
			'render' => 'select',
			'select_options' => array(
				'300x250' => 'Large (300 x 250)',
				'320x50' => 'Small (320 x 50)',
			),
			'default' => '300x250',
		),

	);

	/**
	 * Constructor.
	 *
	 * @since 0.4
	 */
	public function __construct() {
		parent::__construct(
			self::OPTION_KEY,
			self::SECTIONS,
			self::FIELDS
		);
		wp_localize_script( 'instant-articles-option-ads', 'INSTANT_ARTICLES_OPTION_ADS', array(
			'option_field_id_source'     => self::OPTION_KEY . '-ad_source',
			'option_field_id_fan'        => self::OPTION_KEY . '-fan_placement_id',
			'option_field_id_iframe'     => self::OPTION_KEY . '-iframe_url',
			'option_field_id_embed'      => self::OPTION_KEY . '-embed_code',
			'option_field_id_dimensions' => self::OPTION_KEY . '-dimensions',
		) );
	}

	/**
	 * Renders the ad source.
	 *
	 * @param array $args configuration fields for the ad.
	 * @since 0.4
	 */
	public static function custom_render_ad_source( $args ) {
		$id = $args['label_for'];
		$name = $args['serialized_with_group'] . '[ad_source]';

		$description = isset( $args['description'] )
			? '<p class="description">' . esc_html( $args['description'] ) . '</p>'
			: '';

		?>
		<select
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
		>
		<?php foreach ( $args['select_options'] as $ad_source_key => $ad_source_name ) : ?>
			<option
				value="<?php echo esc_attr( $ad_source_key ); ?>"
				<?php echo selected( self::$settings['ad_source'], $ad_source_key ) ?>
			>
			<?php echo esc_html( $ad_source_name ); ?>
			</option>
		<?php endforeach; ?>

		<?php
		$compat_plugins = parent::get_registered_compat( 'instant_articles_compat_registry_ads' );
		asort( $compat_plugins );
		if ( count( $compat_plugins ) > 0 ) :
		?>
			<optgroup
				label="From Supported Plugins"
			>
			<?php foreach ( $compat_plugins as $ad_source_key => $ad_source_info ) : ?>
				<option
					value="<?php echo esc_attr( $ad_source_key ); ?>"
					<?php echo selected( self::$settings['ad_source'], $ad_source_key ) ?>
				>
				<?php echo esc_html( $ad_source_info['name'] ); ?>
				</option>
			<?php endforeach; ?>
			</optgroup>
		<?php endif; ?>

		?>
		</select>
		<?php echo $description; ?>
		<?php
	}

	/**
	 * Sanitize and return all the field values.
	 *
	 * This method receives a payload containing all value for its fields and
	 * should return the same payload after having been sanitized.
	 *
	 * Do not, encode the payload as this is performed by the
	 * universal_sanitize_and_encode_handler() of the parent class.
	 *
	 * @param array $field_values The array map with field values.
	 * @since 0.5
	 */
	public function sanitize_option_fields( $field_values ) {
		foreach ( $field_values as $field_id => $field_value ) {
			$field = self::FIELDS[ $field_id ];

			switch ( $field_id ) {
				case 'ad_source':
					$all_options = array();

					$registered_compat_ads = Instant_Articles_Option::get_registered_compat(
						'instant_articles_compat_registry_ads'
					);

					foreach ( $field['select_options'] as $option_id => $option_info ) {
						$all_options[] = $option_id;
					}
					foreach ( $registered_compat_ads as $compat_id => $compat_info ) {
						$all_options[] = $compat_id;
					}

					if ( ! in_array( $field_value, $all_options ) ) {
						$field_values[ $field_id ] = $field['default'];
						add_settings_error(
							$field_id,
							'invalid_option',
							'Invalid Ad Source'
						);
					}
				break;

				case 'fan_placement_id':
					if ( isset( $field_values['ad_source'] ) && 'fan' === $field_values['ad_source'] ) {
						if (
							! is_numeric( $field_values[ $field_id ] ) ||
							strval( absint( $field_values[ $field_id ] ) ) !== $field_values[ $field_id ]
						) {
							add_settings_error(
								$field_id,
								'invalid_placement_id',
								'Invalid Audience Network Placement ID provided'
							);
							$field_values[ $field_id ] = $field['default'];
						} else {
							$field_values[ $field_id ] = absint( $field_values[ $field_id ] );
						}
					}
				break;

				case 'iframe_url':
					if ( isset( $field_values['ad_source'] ) && 'iframe' === $field_values['ad_source'] ) {
						$url = $field_values[$field_id];
						if ( substr( $url, 0, 2 ) === '//' ) {
							// Allow URLs without protocol prefix
							$url = 'http:' . $url;
						}
						$url = filter_var( $url , FILTER_VALIDATE_URL );
						if ( ! $url ) {
							$field_values[ $field_id ] = $field['default'];
							add_settings_error(
								$field_id,
								'invalid_url',
								'Invalid URL provided for Ad iframe'
							);
						}
					}
				break;

				case 'embed_code':
					if ( isset( $field_values['ad_source'] ) && 'embed' === $field_values['ad_source'] ) {
						$document = new DOMDocument();
						$fragment = $document->createDocumentFragment();
						if ( ! @$fragment->appendXML( $field_values[ $field_id ] ) ) {
							add_settings_error(
								'embed_code',
								'invalid_markup',
								'Invalid HTML markup provided for ad custom embed code'
							);
						}
					}
				break;

				case 'dimensions':
					if ( isset( $field_values['ad_source'] ) && 'none' !== $field_values['ad_source'] ) {
						if ( ! array_key_exists( $field_value, $field['select_options'] ) ) {
							$field_values[ $field_id ] = $field['default'];
							add_settings_error(
								'embed_code',
								'invalid_dimensions',
								'Invalid dimensions provided for Ad'
							);
						}
					}
				break;
			}
		}

		return $field_values;
	}
}
