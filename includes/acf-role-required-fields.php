<?php
/**
 * Role-dependent required ACF fields on mitglied posts.
 *
 * Central rule registry for fields whose required state depends on assigned member roles.
 * Extend via the {@see 'soe_acf_role_required_field_rules'} filter.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns role-dependent required-field rules keyed by ACF field name.
 *
 * Rule keys per field:
 * - optional_when_only_roles (string[]): field is optional only when the member has
 *   at least one role and ALL assigned roles are in this list.
 * - required_message (string): validation error shown when value is empty but required.
 * - instructions_when_required (string): appended to field instructions when required.
 * - instructions_roles (string[]): only append instructions when member has one of these roles.
 *
 * @return array<string, array<string, mixed>>
 */
function soe_get_acf_role_required_field_rules() {
	$rules = array(
		'telefonnummer' => array(
			'optional_when_only_roles'   => array( SOE_ROLE_ATHLET_IN ),
			'required_message'           => __( 'Telefonnummer ist erforderlich.', 'special-olympics-extension' ),
			'instructions_when_required' => __( 'Diese Nummer wird bei von dir erfassten Athleten als Notfallkontakt-Telefon verwendet.', 'special-olympics-extension' ),
			'instructions_roles'         => array( 'ansprechperson' ),
		),
	);

	return apply_filters( 'soe_acf_role_required_field_rules', $rules );
}

/**
 * Normalizes role values to a list of non-empty string slugs.
 *
 * @param mixed $roles Raw role value (array, string, or other).
 * @return string[]
 */
function soe_mitglied_normalize_role_slugs( $roles ) {
	if ( is_string( $roles ) && $roles !== '' ) {
		$roles = array( $roles );
	}
	if ( ! is_array( $roles ) ) {
		return array();
	}

	$normalized = array();
	foreach ( $roles as $role ) {
		$role = is_string( $role ) ? trim( $role ) : '';
		if ( $role !== '' ) {
			$normalized[] = $role;
		}
	}

	return array_values( array_unique( $normalized ) );
}

/**
 * Whether an ACF field is required for the given member roles.
 *
 * @param string   $field_name ACF field name.
 * @param string[] $roles      Member role slugs.
 * @return bool
 */
function soe_acf_field_is_required_for_mitglied_roles( $field_name, $roles ) {
	$rules = soe_get_acf_role_required_field_rules();
	if ( ! isset( $rules[ $field_name ] ) ) {
		return false;
	}

	$roles = soe_mitglied_normalize_role_slugs( $roles );
	if ( empty( $roles ) ) {
		return false;
	}

	$optional_only = isset( $rules[ $field_name ]['optional_when_only_roles'] )
		? soe_mitglied_normalize_role_slugs( $rules[ $field_name ]['optional_when_only_roles'] )
		: array();

	foreach ( $roles as $role ) {
		if ( ! in_array( $role, $optional_only, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Resolves role slugs for a mitglied post.
 *
 * Reads WP user roles (authoritative) first, falls back to ACF post meta.
 * Does NOT call get_field() to avoid recursion during prepare_field.
 *
 * @param int $post_id Mitglied post ID.
 * @return string[]
 */
function soe_get_mitglied_roles_for_required_check( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 ) {
		return array();
	}

	// WP user roles are authoritative for linked accounts.
	if ( function_exists( 'soe_mitglied_get_linked_user_id' ) ) {
		$user_id = soe_mitglied_get_linked_user_id( $post_id );
		if ( $user_id > 0 ) {
			$user = get_user_by( 'ID', $user_id );
			if ( $user && is_array( $user->roles ) ) {
				$wp_roles = array_values( array_intersect( $user->roles, soe_get_special_olympics_roles() ) );
				if ( ! empty( $wp_roles ) ) {
					return $wp_roles;
				}
			}
		}
	}

	// Fallback: read serialized ACF role value from post meta directly.
	$raw = get_post_meta( $post_id, 'role', true );
	if ( is_string( $raw ) ) {
		$raw = maybe_unserialize( $raw );
	}

	return soe_mitglied_normalize_role_slugs( $raw );
}

/**
 * Returns the current mitglied post ID being edited.
 *
 * Uses global $post first (reliable on admin edit screens), then $_REQUEST['post'].
 * Returns 0 when not on a mitglied edit screen.
 *
 * @return int
 */
function soe_get_current_mitglied_post_id() {
	global $post;

	if ( $post && isset( $post->ID ) && $post->post_type === 'mitglied' ) {
		return (int) $post->ID;
	}

	if ( isset( $_REQUEST['post'] ) && is_numeric( $_REQUEST['post'] ) ) {
		$post_id = (int) $_REQUEST['post'];
		if ( $post_id > 0 && get_post_type( $post_id ) === 'mitglied' ) {
			return $post_id;
		}
	}

	return 0;
}

/**
 * Reads role slugs submitted in the current ACF save request.
 *
 * @return string[]
 */
function soe_get_acf_post_request_role_slugs() {
	if ( empty( $_POST['acf'] ) || ! is_array( $_POST['acf'] ) ) {
		return array();
	}

	$role_field = function_exists( 'acf_get_field' ) ? acf_get_field( 'role' ) : null;
	if ( ! is_array( $role_field ) || empty( $role_field['key'] ) ) {
		return array();
	}

	$key = $role_field['key'];
	if ( ! isset( $_POST['acf'][ $key ] ) ) {
		return array();
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by normalize.
	return soe_mitglied_normalize_role_slugs( wp_unslash( $_POST['acf'][ $key ] ) );
}

/**
 * Sets required flag and optional instructions on ACF fields with role-dependent rules.
 *
 * Hooks into each managed field name directly (acf/prepare_field/name=…) for reliability.
 *
 * @param array $field ACF field array.
 * @return array
 */
/**
 * Returns a prepare_field callback that applies role-dependent required rules for a specific field name.
 *
 * Using a closure ensures the ACF field name (from the rule registry) is always available,
 * regardless of what ACF stores in $field['name'] at callback time (which may be the HTML
 * input name rather than the original ACF field slug).
 *
 * @param string $rule_field_name ACF field name slug as used in soe_get_acf_role_required_field_rules().
 * @return Closure
 */
function soe_acf_make_role_required_callback( $rule_field_name ) {
	return function ( $field ) use ( $rule_field_name ) {
		if ( ! is_array( $field ) ) {
			return $field;
		}

		$post_id = soe_get_current_mitglied_post_id();
		if ( $post_id <= 0 ) {
			return $field;
		}

		// On save: prefer submitted roles (not yet persisted).
		$roles = soe_get_acf_post_request_role_slugs();
		if ( empty( $roles ) ) {
			$roles = soe_get_mitglied_roles_for_required_check( $post_id );
		}

		$rules       = soe_get_acf_role_required_field_rules();
		$is_required = soe_acf_field_is_required_for_mitglied_roles( $rule_field_name, $roles );

		$field['required'] = $is_required ? 1 : 0;

		if ( $is_required && isset( $rules[ $rule_field_name ]['instructions_when_required'] ) ) {
			$instruction_roles = isset( $rules[ $rule_field_name ]['instructions_roles'] )
				? soe_mitglied_normalize_role_slugs( $rules[ $rule_field_name ]['instructions_roles'] )
				: array();
			$show = empty( $instruction_roles ) || ! empty( array_intersect( $roles, $instruction_roles ) );
			if ( $show ) {
				$tip = trim( (string) $rules[ $rule_field_name ]['instructions_when_required'] );
				// Store tooltip text as a data attribute on the field wrapper.
				// JS picks this up and inserts a "?" badge next to the label.
				if ( ! isset( $field['wrapper'] ) || ! is_array( $field['wrapper'] ) ) {
					$field['wrapper'] = array( 'width' => '', 'class' => '', 'id' => '' );
				}
				$field['wrapper']['data-soe-tooltip'] = $tip;
			}
		}

		return $field;
	};
}

/**
 * Validates role-dependent required ACF fields on save.
 *
 * @param mixed  $valid Validation state or error message.
 * @param mixed  $value Submitted value.
 * @param array  $field ACF field array.
 * @param string $input Input element name attribute.
 * @return mixed
 */
function soe_acf_validate_role_required_field( $valid, $value, $field, $input ) {
	if ( $valid !== true || ! is_array( $field ) || empty( $field['name'] ) ) {
		return $valid;
	}

	$rules = soe_get_acf_role_required_field_rules();
	if ( ! isset( $rules[ $field['name'] ] ) ) {
		return $valid;
	}

	$post_id = soe_get_current_mitglied_post_id();
	if ( $post_id <= 0 ) {
		return $valid;
	}

	$roles = soe_get_acf_post_request_role_slugs();
	if ( empty( $roles ) ) {
		$roles = soe_get_mitglied_roles_for_required_check( $post_id );
	}

	if ( ! soe_acf_field_is_required_for_mitglied_roles( $field['name'] ?? '', $roles ) ) {
		return $valid;
	}

	if ( trim( (string) $value ) === '' ) {
		return isset( $rules[ $field['name'] ]['required_message'] )
			? (string) $rules[ $field['name'] ]['required_message']
			: __( 'Dieses Feld ist erforderlich.', 'special-olympics-extension' );
	}

	return $valid;
}
add_filter( 'acf/validate_value', 'soe_acf_validate_role_required_field', 10, 4 );

/**
 * Returns true when the current admin screen is a mitglied post edit screen.
 *
 * @return bool
 */
function soe_is_mitglied_edit_screen() {
	if ( ! is_admin() ) {
		return false;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return false;
	}
	return $screen->post_type === 'mitglied' && in_array( $screen->base, array( 'post', 'post-new' ), true );
}

/**
 * Outputs tooltip CSS directly in <head> on mitglied edit screens.
 */
function soe_acf_field_tooltip_head_styles() {
	if ( ! soe_is_mitglied_edit_screen() ) {
		return;
	}
	?>
	<style id="soe-acf-field-tips">
	/* SOE ACF field tooltips --------------------------------------------------- */
	.soe-field-tip {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 15px;
		height: 15px;
		border: 1px solid #aaa;
		border-radius: 50%;
		font-size: 10px;
		font-weight: 600;
		color: #646970;
		background: #f6f7f7;
		cursor: default;
		margin-left: 5px;
		position: relative;
		vertical-align: middle;
		line-height: 1;
		flex-shrink: 0;
	}
	.soe-field-tip::after {
		content: attr(data-tip);
		position: absolute;
		bottom: calc(100% + 7px);
		left: 50%;
		transform: translateX(-50%);
		background: #1d2327;
		color: #fff;
		padding: 7px 11px;
		border-radius: 4px;
		font-size: 12px;
		font-weight: 400;
		white-space: normal;
		width: 230px;
		text-align: left;
		pointer-events: none;
		opacity: 0;
		transition: opacity .15s ease;
		z-index: 9999;
		line-height: 1.5;
	}
	.soe-field-tip::before {
		content: "";
		position: absolute;
		bottom: calc(100% + 1px);
		left: 50%;
		transform: translateX(-50%);
		border: 5px solid transparent;
		border-top-color: #1d2327;
		pointer-events: none;
		opacity: 0;
		transition: opacity .15s ease;
		z-index: 9999;
	}
	.soe-field-tip:hover::after,
	.soe-field-tip:hover::before {
		opacity: 1;
	}
	/* Hide original ACF instructions text – now shown as tooltip */
	.acf-field .acf-instructions,
	.acf-field .description {
		display: none !important;
	}
	/* -------------------------------------------------------------------------- */
	</style>
	<?php
}
add_action( 'admin_head', 'soe_acf_field_tooltip_head_styles' );

/**
 * Outputs tooltip JS directly before </body> on mitglied edit screens.
 * Converts ACF field instructions into "?" tooltip badges next to labels.
 */
function soe_acf_field_tooltip_footer_script() {
	if ( ! soe_is_mitglied_edit_screen() ) {
		return;
	}
	?>
	<script id="soe-acf-field-tips-js">
	(function () {
		/**
		 * Converts ACF field instructions into a "?" tooltip badge next to the label.
		 * Priority:
		 *   1. data-soe-tooltip attribute (set by role-required rules)
		 *   2. Text content of .acf-instructions (standard ACF instructions)
		 */
		function soeInitFieldTooltips() {
			document.querySelectorAll('.acf-field').forEach(function (wrapper) {
				if (wrapper.querySelector('.soe-field-tip')) return;

				var label = wrapper.querySelector('.acf-label label');
				if (!label) return;

				var tip  = wrapper.getAttribute('data-soe-tooltip') || '';
				var inst = wrapper.querySelector('.acf-instructions') || wrapper.querySelector('.description');

				if (!tip && inst) {
					tip = inst.textContent.trim();
				}

				// Always hide the original instruction element directly (CSS may not cover all cases).
				if (inst) {
					inst.style.display = 'none';
				}

				if (!tip) return;

				var badge = document.createElement('span');
				badge.className = 'soe-field-tip';
				badge.setAttribute('data-tip', tip);
				badge.textContent = '?';
				label.appendChild(badge);
			});
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', soeInitFieldTooltips);
		} else {
			soeInitFieldTooltips();
		}
	})();
	</script>
	<?php
}
add_action( 'admin_footer', 'soe_acf_field_tooltip_footer_script' );

/**
 * Registers acf/prepare_field/name=… hooks for every field in the rule registry.
 * Called on 'acf/init' so the field group JSON is already loaded.
 */
function soe_register_acf_role_required_field_hooks() {
	$rules = soe_get_acf_role_required_field_rules();
	foreach ( array_keys( $rules ) as $field_name ) {
		add_filter(
			'acf/prepare_field/name=' . $field_name,
			soe_acf_make_role_required_callback( $field_name ),
			20
		);
	}
}
add_action( 'acf/init', 'soe_register_acf_role_required_field_hooks' );
