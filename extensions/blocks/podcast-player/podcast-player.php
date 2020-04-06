<?php
/**
 * Podcast Player Block.
 *
 * @since 8.4.0
 *
 * @package Jetpack
 */

namespace Automattic\Jetpack\Extensions\Podcast_Player;

use WP_Error;
use Jetpack_Gutenberg;
use Jetpack_Podcast_Helper;
use Jetpack_AMP_Support;

const FEATURE_NAME = 'podcast-player';
const BLOCK_NAME   = 'jetpack/' . FEATURE_NAME;

if ( ! class_exists( 'Jetpack_Podcast_Helper' ) ) {
	\jetpack_require_lib( 'class-jetpack-podcast-helper' );
}

/**
 * Registers the block for use in Gutenberg
 * This is done via an action so that we can disable
 * registration if we need to.
 */
function register_block() {
	jetpack_register_block(
		BLOCK_NAME,
		array(
			'attributes'      => array(
				'url'                    => array(
					'type' => 'url',
				),
				'itemsToShow'            => array(
					'type'    => 'integer',
					'default' => 5,
				),
				'showCoverArt'           => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showEpisodeDescription' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
			'render_callback' => __NAMESPACE__ . '\render_block',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\register_block' );

/**
 * Podcast Player block registration/dependency declaration.
 *
 * @param array $attributes Array containing the Podcast Player block attributes.
 * @return string
 */
function render_block( $attributes ) {

	// Test for empty URLS.
	if ( empty( $attributes['url'] ) ) {
		return '<p>' . esc_html__( 'No Podcast URL provided. Please enter a valid Podcast RSS feed URL.', 'jetpack' ) . '</p>';
	}

	// Test for invalid URLs.
	if ( ! wp_http_validate_url( $attributes['url'] ) ) {
		return '<p>' . esc_html__( 'Your podcast URL is invalid and couldn\'t be embedded. Please double check your URL.', 'jetpack' ) . '</p>';
	}

	// Sanitize the URL.
	$attributes['url'] = esc_url_raw( $attributes['url'] );

	$player_data = Jetpack_Podcast_Helper::get_player_data( $attributes['url'] );

	if ( is_wp_error( $player_data ) ) {
		return '<p>' . esc_html( $player_data->get_error_message() ) . '</p>';
	}

	return render_player( $player_data, $attributes );
}

/**
 * Renders the HTML for the Podcast player and tracklist.
 *
 * @param array $player_data The player data details.
 * @param array $attributes Array containing the Podcast Player block attributes.
 * @return string The HTML for the podcast player.
 */
function render_player( $player_data, $attributes ) {
	// If there are no tracks (it is possible) then display appropriate user facing error message.
	if ( empty( $player_data['tracks'] ) ) {
		return '<p>' . esc_html__( 'No tracks available to play.', 'jetpack' ) . '</p>';
	}

	// Only use the amount of tracks requested.
	$player_data['tracks'] = array_slice(
		$player_data['tracks'],
		0,
		absint( $attributes['itemsToShow'] )
	);

	// Generate a unique id for the block instance.
	$instance_id             = wp_unique_id( 'jetpack-podcast-player-block-' );
	$player_data['playerId'] = $instance_id;

	// Check if we are in AMP mode.
	$is_amp                = ( class_exists( 'Jetpack_AMP_Support' ) && Jetpack_AMP_Support::is_amp_request() );
	$player_data['is_amp'] = $is_amp;

	// Generate object to be used as props for PodcastPlayer.
	$player_props = array_merge(
		// Add all attributes.
		array( 'attributes' => $attributes ),
		// Add all player data.
		$player_data
	);

	$primary_colors    = get_colors( 'primary', $attributes, 'color' );
	$secondary_colors  = get_colors( 'secondary', $attributes, 'color' );
	$background_colors = get_colors( 'background', $attributes, 'background-color' );

	$player_classes_name = trim( "{$secondary_colors['class']} {$background_colors['class']}" );
	$player_inline_style = trim( "{$secondary_colors['style']} ${background_colors['style']}" );

	$extra_classes = array( 'is-default' );
	if ( $is_amp ) {
		$extra_classes[] = 'is-amp';
	}
	$block_classname = Jetpack_Gutenberg::block_classes( FEATURE_NAME, $attributes, $extra_classes );

	ob_start();
	?>
	<div class="<?php echo esc_attr( $block_classname ); ?>" id="<?php echo esc_attr( $instance_id ); ?>">
		<section
			class="jetpack-podcast-player <?php echo esc_attr( $player_classes_name ); ?>"
			style="<?php echo esc_attr( $player_inline_style ); ?>"
		>
			<?php
			render(
				'podcast-header',
				array_merge(
					$player_props,
					array(
						'primary_colors' => $primary_colors,
						'player_id'      => $player_data['playerId'],
					)
				)
			);
			?>
			<?php if ( $is_amp ) : ?>
			<amp-selector
				layout="container"
				keyboard-select-mode="select"
				class="jetpack-podcast-player__tracks"
				on="select: AMP.setState({
					currentTrack: event.targetOption,
				})"
			>
			<?php else : ?>
			<ol class="jetpack-podcast-player__tracks">
			<?php endif; ?>
				<?php foreach ( $player_data['tracks'] as $track_index => $attachment ) : ?>
					<?php
					render(
						'playlist-track',
						array(
							'index'            => $track_index,
							'is_active'        => 0 === $track_index,
							'attachment'       => $attachment,
							'primary_colors'   => $primary_colors,
							'secondary_colors' => $secondary_colors,
							'is_amp'           => $is_amp,
						)
					);
					?>
				<?php endforeach; ?>
			<?php if ( $is_amp ) : ?>
			</amp-selector>
			<?php else : ?>
			</ol>
			<?php endif; ?>
		</section>
		<?php if ( $is_amp ) : ?>
		<amp-state id="podcastPlayer">
		<?php endif; ?>
		<script type="application/json"><?php echo wp_json_encode( $player_props ); ?></script>
		<?php if ( $is_amp ) : ?>
		</amp-state>
		<?php endif; ?>
	</div>
	<?php if ( ! $is_amp ) : ?>
	<script>
		( function( instanceId ) {
			document.getElementById( instanceId ).classList.remove( 'is-default' );
			window.jetpackPodcastPlayers=(window.jetpackPodcastPlayers||[]);
			window.jetpackPodcastPlayers.push( instanceId );
		} )( <?php echo wp_json_encode( $instance_id ); ?> );
	</script>
	<?php endif; ?>
	<?php
	/**
	 * Enqueue necessary scripts and styles.
	 */
	if ( ! $is_amp ) {
		wp_enqueue_style( 'wp-mediaelement' );
	}
	Jetpack_Gutenberg::load_assets_as_required( FEATURE_NAME, array( 'mediaelement' ) );

	return ob_get_clean();
}

/**
 * Given the color name, block attributes and the CSS property,
 * the function will return an array with the `class` and `style`
 * HTML attributes to be used straight in the markup.
 *
 * @example
 * $color = get_colors( 'secondary', $attributes, 'border-color'
 *  => array( 'class' => 'has-secondary', 'style' => 'border-color: #333' )
 *
 * @param string $name     Color attribute name, for instance `primary`, `secondary`, ...
 * @param array  $attrs    Block attributes.
 * @param string $property Color CSS property, fo instance `color`, `background-color`, ...
 * @return array           Colors array.
 */
function get_colors( $name, $attrs, $property ) {
	$attr_color  = "{$name}Color";
	$attr_custom = 'custom' . ucfirst( $attr_color );

	$color        = isset( $attrs[ $attr_color ] ) ? $attrs[ $attr_color ] : null;
	$custom_color = isset( $attrs[ $attr_custom ] ) ? $attrs[ $attr_custom ] : null;

	$colors = array(
		'class' => '',
		'style' => '',
	);

	if ( $color || $custom_color ) {
		$colors['class'] .= "has-{$name}";

		if ( $color ) {
			$colors['class'] .= " has-{$color}-{$property}";
		} elseif ( $custom_color ) {
			$colors['style'] .= "{$property}: {$custom_color};";
		}
	}

	return $colors;
}

/**
 * Render the given template in server-side.
 * Important note:
 *    The $template_props array will be extracted.
 *    This means it will create a var for each array item.
 *    Keep it mind when using this param to pass
 *    properties to the template.
 *
 * @param string $name           Template name, available in `./templates` folder.
 * @param array  $template_props Template properties. Optional.
 * @param bool   $print          Render template. True as default.
 * @return false|string          HTML markup or false.
 */
function render( $name, $template_props = array(), $print = true ) {
	if ( ! strpos( $name, '.php' ) ) {
		$name = $name . '.php';
	}

	$template_path = dirname( __FILE__ ) . '/templates/' . $name;

	if ( ! file_exists( $template_path ) ) {
		return '';
	}

	// Optionally provided an assoc array of data to pass to template
	// IMPORTANT: It will be extracted into variables.
	if ( is_array( $template_props ) ) {
		// It ignores the `discouraging` sniffer rule for extract,
		// since it's needed to make the templating system works.
		extract( $template_props ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	}

	if ( $print ) {
		include $template_path;
	} else {
		ob_start();
		include $template_path;
		$markup = ob_get_contents();
		ob_end_clean();

		return $markup;
	}
}
