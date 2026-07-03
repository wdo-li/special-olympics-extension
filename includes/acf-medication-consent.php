<?php
/**
 * Conditional medication consent fields on mitglied posts.
 *
 * ACF conditional logic cannot show parent-level fields based on repeater sub-fields.
 * Visibility and required state are handled via admin JS + CSS; validation is enforced on save.
 *
 * Note: ACF's wrapper class "acf-hidden" does not hide regular fields (no CSS rule).
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** CSS class used to hide consent fields when no medication data exists. */
define( 'SOE_MEDICATION_CONSENT_HIDDEN_CLASS', 'soe-medication-consent-hidden' );

/** ACF field key: repeater Medikamentangaben. */
const SOE_ACF_FIELD_MEDIKAMENTANGABEN = 'field_682b36ce17bbe';

/** ACF field key: repeater Notfallmedikamente. */
const SOE_ACF_FIELD_NOTFALLMEDIKAMENTE = 'field_6978bea705af8';

/** ACF field key: consent Medikamentenabgabe. */
const SOE_ACF_FIELD_ZUSTIMMUNG_MEDIKAMENTE = 'field_6a477cb605f1b';

/** ACF field key: consent Notfallmedikamentenabgabe. */
const SOE_ACF_FIELD_ZUSTIMMUNG_NOTFALL = 'field_6a477d901f4d3';

/**
 * Maps consent field keys to related repeater field keys.
 *
 * @return array<string, string>
 */
function soe_get_medication_consent_repeater_map_by_key() {
	return array(
		SOE_ACF_FIELD_ZUSTIMMUNG_MEDIKAMENTE => SOE_ACF_FIELD_MEDIKAMENTANGABEN,
		SOE_ACF_FIELD_ZUSTIMMUNG_NOTFALL      => SOE_ACF_FIELD_NOTFALLMEDIKAMENTE,
	);
}

/**
 * Whether the current request should run medication consent save/validation logic.
 *
 * @param int $post_id Mitglied post ID.
 * @return bool
 */
function soe_acf_should_apply_medication_consent_rules( $post_id ) {
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || get_post_type( $post_id ) !== 'mitglied' ) {
		return false;
	}

	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return false;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return false;
	}

	return true;
}

/**
 * Returns medication consent/repeater pairs for admin JS.
 *
 * @return array<int, array{repeaterKey: string, consentKey: string}>
 */
function soe_get_medication_consent_pairs_for_js() {
	$pairs = array();

	foreach ( soe_get_medication_consent_repeater_map_by_key() as $consent_key => $repeater_key ) {
		$pairs[] = array(
			'repeaterKey' => $repeater_key,
			'consentKey'  => $consent_key,
		);
	}

	return $pairs;
}

/**
 * Resolves the mitglied post ID during admin render, validation, and save.
 *
 * @return int
 */
function soe_get_mitglied_post_id_for_acf_context() {
	if ( function_exists( 'soe_get_current_mitglied_post_id' ) ) {
		$post_id = soe_get_current_mitglied_post_id();
		if ( $post_id > 0 ) {
			return $post_id;
		}
	}

	$candidates = array( 'post_ID', 'post_id' );
	foreach ( $candidates as $key ) {
		if ( empty( $_POST[ $key ] ) || ! is_numeric( $_POST[ $key ] ) ) {
			continue;
		}
		$post_id = (int) $_POST[ $key ];
		if ( $post_id > 0 && get_post_type( $post_id ) === 'mitglied' ) {
			return $post_id;
		}
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
 * ACF internal repeater row keys that must not count as medication data.
 *
 * @param string $key Row array key from get_field() or $_POST['acf'].
 * @return bool
 */
function soe_acf_is_repeater_internal_row_key( $key ) {
	$key = (string) $key;

	if ( $key === '' || $key === 'acfcloneindex' ) {
		return true;
	}

	return $key[0] === '_';
}

/**
 * Whether a single repeater cell value counts as medication data.
 *
 * @param mixed $value Cell value.
 * @return bool
 */
function soe_acf_repeater_cell_has_data( $value ) {
	return is_string( $value ) && trim( $value ) !== '';
}

/**
 * Whether a repeater row set contains at least one non-empty sub-field value.
 *
 * @param mixed $rows Repeater rows from get_field() or $_POST['acf'].
 * @return bool
 */
function soe_acf_repeater_rows_have_data( $rows ) {
	if ( ! is_array( $rows ) || empty( $rows ) ) {
		return false;
	}

	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		foreach ( $row as $key => $value ) {
			if ( soe_acf_is_repeater_internal_row_key( $key ) ) {
				continue;
			}
			if ( soe_acf_repeater_cell_has_data( $value ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Reads repeater rows submitted in the current ACF save request.
 *
 * @param string $repeater_field_key ACF field key of the repeater.
 * @return array<int, array<string, mixed>>|null Null when not present in POST.
 */
function soe_get_acf_post_repeater_rows( $repeater_field_key ) {
	if ( empty( $_POST['acf'] ) || ! is_array( $_POST['acf'] ) ) {
		return null;
	}

	if ( ! array_key_exists( $repeater_field_key, $_POST['acf'] ) ) {
		return null;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- structure checked in helper.
	$rows = wp_unslash( $_POST['acf'][ $repeater_field_key ] );

	return is_array( $rows ) ? $rows : array();
}

/**
 * Reads a consent true/false value from the current ACF save request.
 *
 * @param string $consent_field_key ACF field key of the consent field.
 * @return mixed Null when the field is absent from POST.
 */
function soe_get_acf_post_consent_value( $consent_field_key ) {
	if ( empty( $_POST['acf'] ) || ! is_array( $_POST['acf'] ) ) {
		return null;
	}

	if ( ! array_key_exists( $consent_field_key, $_POST['acf'] ) ) {
		return null;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as consent flag below.
	return wp_unslash( $_POST['acf'][ $consent_field_key ] );
}

/**
 * Whether a submitted consent true/false value is checked.
 *
 * @param mixed $value Raw consent value.
 * @return bool
 */
function soe_acf_consent_value_is_checked( $value ) {
	return $value === 1 || $value === '1' || $value === true;
}

/**
 * Whether the related medication repeater has data for the current edit/save context.
 *
 * @param string $repeater_field_key ACF field key of the repeater.
 * @param int    $post_id            Mitglied post ID.
 * @return bool
 */
function soe_medication_repeater_has_data( $repeater_field_key, $post_id ) {
	$post_rows = soe_get_acf_post_repeater_rows( $repeater_field_key );
	if ( $post_rows !== null ) {
		return soe_acf_repeater_rows_have_data( $post_rows );
	}

	if ( $post_id <= 0 || ! function_exists( 'get_field' ) ) {
		return false;
	}

	$field = acf_get_field( $repeater_field_key );
	$name  = is_array( $field ) && ! empty( $field['name'] ) ? $field['name'] : '';

	if ( $name === '' ) {
		return false;
	}

	return soe_acf_repeater_rows_have_data( get_field( $name, $post_id, false ) );
}

/**
 * Adds or removes the hidden CSS class on a field wrapper.
 *
 * @param array $field    ACF field array.
 * @param bool  $has_data Whether related repeater has data.
 * @return array
 */
function soe_acf_set_medication_consent_wrapper_visibility( $field, $has_data ) {
	if ( ! isset( $field['wrapper'] ) || ! is_array( $field['wrapper'] ) ) {
		$field['wrapper'] = array(
			'width' => '',
			'class' => '',
			'id'    => '',
		);
	}

	$hidden_class  = SOE_MEDICATION_CONSENT_HIDDEN_CLASS;
	$wrapper_class = trim( (string) ( $field['wrapper']['class'] ?? '' ) );
	$wrapper_class = preg_replace( '/\b' . preg_quote( $hidden_class, '/' ) . '\b/', '', $wrapper_class );
	$wrapper_class = trim( preg_replace( '/\s+/', ' ', $wrapper_class ) );

	if ( ! $has_data ) {
		$wrapper_class = trim( $wrapper_class . ' ' . $hidden_class );
	}

	$field['wrapper']['class'] = $wrapper_class;

	return $field;
}

/**
 * Returns the validation error message for a consent field key.
 *
 * @param string $consent_field_key Consent field key.
 * @return string
 */
function soe_get_medication_consent_validation_message( $consent_field_key ) {
	if ( $consent_field_key === SOE_ACF_FIELD_ZUSTIMMUNG_NOTFALL ) {
		return __( 'Bitte bestätigen Sie die Zustimmung zur Notfallmedikamentenabgabe.', 'special-olympics-extension' );
	}

	return __( 'Bitte bestätigen Sie die Zustimmung zur Medikamentenabgabe.', 'special-olympics-extension' );
}

/**
 * Returns a prepare_field callback for a consent field key.
 *
 * @param string $repeater_field_key Related repeater field key.
 * @return Closure
 */
function soe_acf_make_medication_consent_prepare_callback( $repeater_field_key ) {
	return function ( $field ) use ( $repeater_field_key ) {
		if ( ! is_array( $field ) ) {
			return $field;
		}

		$post_id  = soe_get_mitglied_post_id_for_acf_context();
		$has_data = soe_medication_repeater_has_data( $repeater_field_key, $post_id );

		$field['required'] = $has_data ? 1 : 0;

		return soe_acf_set_medication_consent_wrapper_visibility( $field, $has_data );
	};
}

/**
 * Validates medication consent fields on save even when ACF omits unchecked values from POST.
 */
function soe_acf_validate_medication_consent_on_save_post() {
	$post_id = soe_get_mitglied_post_id_for_acf_context();
	if ( ! soe_acf_should_apply_medication_consent_rules( $post_id ) ) {
		return;
	}

	foreach ( soe_get_medication_consent_repeater_map_by_key() as $consent_key => $repeater_key ) {
		if ( ! soe_medication_repeater_has_data( $repeater_key, $post_id ) ) {
			continue;
		}

		$value = soe_get_acf_post_consent_value( $consent_key );
		if ( soe_acf_consent_value_is_checked( $value ) ) {
			continue;
		}

		acf_add_validation_error(
			'acf[' . $consent_key . ']',
			soe_get_medication_consent_validation_message( $consent_key )
		);
	}
}

/**
 * Clears stale consent values after save when the related repeater is empty.
 *
 * @param int|string $post_id Post ID passed by ACF.
 */
function soe_acf_cleanup_medication_consent_on_save( $post_id ) {
	$post_id = is_numeric( $post_id ) ? (int) $post_id : 0;
	if ( ! soe_acf_should_apply_medication_consent_rules( $post_id ) ) {
		return;
	}

	if ( ! function_exists( 'update_field' ) ) {
		return;
	}

	foreach ( soe_get_medication_consent_repeater_map_by_key() as $consent_key => $repeater_key ) {
		if ( soe_medication_repeater_has_data( $repeater_key, $post_id ) ) {
			continue;
		}

		$field = acf_get_field( $consent_key );
		if ( ! is_array( $field ) || empty( $field['name'] ) ) {
			continue;
		}

		update_field( $field['name'], 0, $post_id );
	}
}

/**
 * Outputs CSS to hide medication consent fields on mitglied edit screens.
 */
function soe_medication_consent_admin_styles() {
	if ( ! function_exists( 'soe_is_mitglied_edit_screen' ) || ! soe_is_mitglied_edit_screen() ) {
		return;
	}

	$hidden_class = SOE_MEDICATION_CONSENT_HIDDEN_CLASS;
	?>
	<style id="soe-medication-consent-styles">
		.acf-field.<?php echo esc_attr( $hidden_class ); ?> {
			display: none !important;
		}
	</style>
	<?php
}
add_action( 'admin_head', 'soe_medication_consent_admin_styles' );

/**
 * Registers ACF hooks for medication consent fields.
 */
function soe_register_acf_medication_consent_hooks() {
	foreach ( soe_get_medication_consent_repeater_map_by_key() as $consent_key => $repeater_key ) {
		add_filter(
			'acf/prepare_field/key=' . $consent_key,
			soe_acf_make_medication_consent_prepare_callback( $repeater_key ),
			20
		);
	}

	add_action( 'acf/validate_save_post', 'soe_acf_validate_medication_consent_on_save_post', 20 );
	add_action( 'acf/save_post', 'soe_acf_cleanup_medication_consent_on_save', 25 );
}
add_action( 'acf/init', 'soe_register_acf_medication_consent_hooks' );
