<?php
/**
 * Duration tiers and payroll hourly rate configuration.
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Payroll role/stufe slots used to build hourly rate keys per duration tier.
 *
 * @return array<int, array{prefix: string, label: string}>
 */
function soe_get_payroll_rate_slots() {
	return array(
		array(
			'prefix' => 'hauptleiter_in_1a',
			'label'  => __( 'Hauptleiter*in 1A', 'special-olympics-extension' ),
		),
		array(
			'prefix' => 'hauptleiter_in_1b',
			'label'  => __( 'Hauptleiter*in 1B', 'special-olympics-extension' ),
		),
		array(
			'prefix' => 'leiter_in_2a',
			'label'  => __( 'Leiter*in 2A', 'special-olympics-extension' ),
		),
		array(
			'prefix' => 'assistenztrainer_in_2b',
			'label'  => __( 'Assistenztrainer*in 2B', 'special-olympics-extension' ),
		),
		array(
			'prefix' => 'helfer_in_3a',
			'label'  => __( 'Helfer*in 3A', 'special-olympics-extension' ),
		),
		array(
			'prefix' => 'praktikant_in_3b',
			'label'  => __( 'Praktikant*in 3B', 'special-olympics-extension' ),
		),
		array(
			'prefix' => 'schueler_in_3b',
			'label'  => __( 'Schüler*in 3B', 'special-olympics-extension' ),
		),
		array(
			'prefix' => 'athlet_leader_3c',
			'label'  => __( 'Athlete Leader 3C', 'special-olympics-extension' ),
		),
	);
}

/**
 * Default duration tiers for new installations.
 *
 * @return array<int, array{key: string, label: string}>
 */
function soe_get_default_duration_tiers() {
	return array(
		array(
			'key'   => 'd_60min',
			'label' => '60 Minuten',
		),
		array(
			'key'   => 'd_90min',
			'label' => '90 Minuten',
		),
		array(
			'key'   => 'd_halbtag',
			'label' => 'ab 2 Stunden (Halbtagspauschale)',
		),
		array(
			'key'   => 'd_ganztag',
			'label' => 'ab 4 Stunden (Tagespauschale)',
		),
		array(
			'key'   => 'd_hl',
			'label' => 'HL Pauschale',
		),
		array(
			'key'   => 'd_ski',
			'label' => 'Skitraining',
		),
	);
}

/**
 * Sanitizes a duration or rate key (lowercase a-z, 0-9, underscore).
 *
 * @param string $key Raw key.
 * @return string
 */
function soe_sanitize_option_key( $key ) {
	$key = strtolower( trim( (string) $key ) );
	return preg_replace( '/[^a-z0-9_]/', '', $key );
}

/**
 * Generates a unique duration key from a label.
 *
 * @param string        $label    Human-readable label.
 * @param array<string> $existing Existing keys to avoid.
 * @return string
 */
function soe_generate_duration_key( $label, $existing = array() ) {
	$base = soe_sanitize_option_key( sanitize_title( $label ) );
	if ( $base === '' ) {
		$base = 'tier';
	}
	if ( strpos( $base, 'd_' ) !== 0 ) {
		$base = 'd_' . $base;
	}
	$key = $base;
	$i   = 2;
	while ( in_array( $key, $existing, true ) ) {
		$key = $base . '_' . $i;
		++$i;
	}
	return $key;
}

/**
 * Returns configured duration tiers from settings.
 *
 * @return array<int, array{key: string, label: string}>
 */
function soe_get_duration_tiers() {
	$tiers = soe_get_setting( 'duration_tiers' );
	if ( ! is_array( $tiers ) || empty( $tiers ) ) {
		return soe_get_default_duration_tiers();
	}
	$out = array();
	foreach ( $tiers as $tier ) {
		if ( ! is_array( $tier ) ) {
			continue;
		}
		$key   = isset( $tier['key'] ) ? soe_sanitize_option_key( $tier['key'] ) : '';
		$label = isset( $tier['label'] ) ? sanitize_text_field( $tier['label'] ) : '';
		if ( $key === '' || $label === '' ) {
			continue;
		}
		$out[] = array(
			'key'   => $key,
			'label' => $label,
		);
	}
	return $out ? $out : soe_get_default_duration_tiers();
}

/**
 * Returns duration options as key/label pairs for training and event dropdowns.
 *
 * @return array<int, array{key: string, label: string}>
 */
function soe_get_duration_options() {
	return soe_get_duration_tiers();
}

/**
 * Builds a payroll hourly rate key for a role slot and duration tier.
 *
 * @param string $slot_prefix Payroll slot prefix (e.g. leiter_in_2a).
 * @param string $duration_key Duration tier key.
 * @return string
 */
function soe_build_payroll_rate_key( $slot_prefix, $duration_key ) {
	$slot_prefix  = soe_sanitize_option_key( $slot_prefix );
	$duration_key = soe_sanitize_option_key( $duration_key );
	if ( $slot_prefix === '' || $duration_key === '' ) {
		return '';
	}
	return $slot_prefix . '_' . $duration_key;
}

/**
 * Returns all valid payroll rate keys for the given duration tiers.
 *
 * @param array<int, array{key: string, label: string}>|null $tiers Optional tiers; defaults to configured tiers.
 * @return array<string>
 */
function soe_get_all_payroll_rate_keys( $tiers = null ) {
	if ( ! is_array( $tiers ) ) {
		$tiers = soe_get_duration_tiers();
	}
	$keys = array();
	foreach ( $tiers as $tier ) {
		if ( empty( $tier['key'] ) ) {
			continue;
		}
		foreach ( soe_get_payroll_rate_slots() as $slot ) {
			$rate_key = soe_build_payroll_rate_key( $slot['prefix'], $tier['key'] );
			if ( $rate_key !== '' ) {
				$keys[] = $rate_key;
			}
		}
	}
	return $keys;
}

/**
 * Returns hourly rate definitions (rate key => label) derived from tiers and role slots.
 *
 * @return array<string, string>
 */
function soe_get_hourly_rate_definitions() {
	$definitions = array();
	foreach ( soe_get_duration_tiers() as $tier ) {
		foreach ( soe_get_payroll_rate_slots() as $slot ) {
			$rate_key = soe_build_payroll_rate_key( $slot['prefix'], $tier['key'] );
			if ( $rate_key === '' ) {
				continue;
			}
			$definitions[ $rate_key ] = $slot['label'] . ' – ' . $tier['label'];
		}
	}
	return $definitions;
}

/**
 * Returns the display label for a stored duration key.
 *
 * @param string $value Duration key stored in the database.
 * @return string
 */
function soe_get_duration_label( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( $value === '' ) {
		return '';
	}
	foreach ( soe_get_duration_options() as $option ) {
		if ( $option['key'] === $value ) {
			return $option['label'];
		}
	}
	return $value;
}

/**
 * Resolves a stored duration value to a configured duration key.
 *
 * @param string $value Duration key from the database.
 * @return string Empty string when not found.
 */
function soe_normalize_duration_key( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( $value === '' ) {
		return '';
	}
	$sanitized = soe_sanitize_option_key( $value );
	foreach ( soe_get_duration_options() as $option ) {
		if ( $option['key'] === $value || $option['key'] === $sanitized ) {
			return $option['key'];
		}
	}
	return '';
}

/**
 * Sanitizes duration tiers from settings form input.
 *
 * @param array $input Raw duration_tiers POST array.
 * @return array<int, array{key: string, label: string}>
 */
function soe_sanitize_duration_tiers_input( $input ) {
	if ( ! is_array( $input ) ) {
		return soe_get_default_duration_tiers();
	}
	$tiers      = array();
	$used_keys  = array();
	foreach ( $input as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
		if ( $label === '' ) {
			continue;
		}
		$key = isset( $row['key'] ) ? soe_sanitize_option_key( $row['key'] ) : '';
		if ( $key === '' ) {
			$key = soe_generate_duration_key( $label, $used_keys );
		}
		if ( in_array( $key, $used_keys, true ) ) {
			$key = soe_generate_duration_key( $label, $used_keys );
		}
		$used_keys[] = $key;
		$tiers[]     = array(
			'key'   => $key,
			'label' => $label,
		);
	}
	return $tiers ? $tiers : soe_get_default_duration_tiers();
}

/**
 * Sanitizes hourly rates for all slot × tier combinations.
 *
 * @param array                                               $input Raw hourly_rates POST array.
 * @param array<int, array{key: string, label: string}>|null $tiers Sanitized duration tiers.
 * @param array<string, string>                               $prev  Previous stored rates.
 * @return array<string, string>
 */
function soe_sanitize_hourly_rates_input( $input, $tiers, $prev = array() ) {
	if ( ! is_array( $input ) ) {
		$input = array();
	}
	if ( ! is_array( $prev ) ) {
		$prev = array();
	}
	$valid_keys = soe_get_all_payroll_rate_keys( $tiers );
	$out        = array();
	foreach ( $valid_keys as $rate_key ) {
		if ( isset( $input[ $rate_key ] ) && is_string( $input[ $rate_key ] ) ) {
			$val = trim( $input[ $rate_key ] );
			$out[ $rate_key ] = $val === '' ? '' : sanitize_text_field( $val );
		} elseif ( isset( $prev[ $rate_key ] ) && is_string( $prev[ $rate_key ] ) ) {
			$out[ $rate_key ] = $prev[ $rate_key ];
		} else {
			$out[ $rate_key ] = '';
		}
	}
	return $out;
}

/**
 * Renders the rates table for one duration tier (panel body).
 *
 * @param array{key: string, label: string} $tier         Duration tier.
 * @param int                               $index        Form index.
 * @param array<string, string>             $hourly_rates Stored CHF values.
 * @return void
 */
function soe_render_duration_tier_rates_table( $tier, $index, $hourly_rates ) {
	$slots = soe_get_payroll_rate_slots();
	?>
	<table class="widefat striped soe-duration-tier-rates">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Rolle', 'special-olympics-extension' ); ?></th>
				<th style="width: 100px;">CHF</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $slots as $slot ) : ?>
				<?php
				$rate_key = soe_build_payroll_rate_key( $slot['prefix'], $tier['key'] );
				if ( $rate_key === '' ) {
					continue;
				}
				$field_id = 'soe_hr_' . $index . '_' . $slot['prefix'];
				?>
				<tr>
					<td><label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $slot['label'] ); ?></label></td>
					<td>
						<input
							type="text"
							name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[hourly_rates][<?php echo esc_attr( $rate_key ); ?>]"
							id="<?php echo esc_attr( $field_id ); ?>"
							value="<?php echo esc_attr( isset( $hourly_rates[ $rate_key ] ) ? $hourly_rates[ $rate_key ] : '' ); ?>"
							class="small-text soe-hourly-rate-input"
							data-rate-key="<?php echo esc_attr( $rate_key ); ?>"
							data-slot-prefix="<?php echo esc_attr( $slot['prefix'] ); ?>"
						/>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Renders duration tiers and hourly rates settings section.
 *
 * @param array<int, array{key: string, label: string}> $duration_tiers Configured tiers.
 * @param array<string, string>                       $hourly_rates   Stored CHF values.
 * @return void
 */
function soe_render_duration_payroll_settings_section( $duration_tiers, $hourly_rates ) {
	$slots = soe_get_payroll_rate_slots();
	?>
	<tr id="soe-card-payroll-duration">
		<th scope="row"><?php esc_html_e( 'Dauer-Stufen & Stundensätze', 'special-olympics-extension' ); ?></th>
		<td>
			<div class="soe-duration-settings">
				<p class="description">
					<?php esc_html_e( 'Lege die Dauer-Optionen für Events und Trainings fest. Wähle unten eine Stufe aus, um die Stundensätze je Rolle zu bearbeiten. Leer lassen = CHF 0.', 'special-olympics-extension' ); ?>
				</p>

				<div class="soe-duration-settings-section">
					<h4 class="soe-duration-settings-subheading"><?php esc_html_e( 'Dauer-Stufen', 'special-olympics-extension' ); ?></h4>
					<div id="soe-duration-tier-list" class="soe-duration-tier-list">
						<?php foreach ( $duration_tiers as $index => $tier ) : ?>
							<div class="soe-duration-tier-row" data-tier-index="<?php echo esc_attr( (string) $index ); ?>" data-tier-key="<?php echo esc_attr( $tier['key'] ); ?>">
								<input
									type="text"
									name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[duration_tiers][<?php echo esc_attr( (string) $index ); ?>][label]"
									value="<?php echo esc_attr( $tier['label'] ); ?>"
									class="regular-text soe-duration-label-input"
									placeholder="<?php esc_attr_e( 'Bezeichnung', 'special-olympics-extension' ); ?>"
									required
								/>
								<input
									type="hidden"
									name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[duration_tiers][<?php echo esc_attr( (string) $index ); ?>][key]"
									value="<?php echo esc_attr( $tier['key'] ); ?>"
									class="soe-duration-key-input"
								/>
								<button type="button" class="button button-link-delete soe-remove-duration-tier" <?php echo count( $duration_tiers ) <= 1 ? 'disabled' : ''; ?>>
									<?php esc_html_e( 'Entfernen', 'special-olympics-extension' ); ?>
								</button>
							</div>
						<?php endforeach; ?>
					</div>
					<p class="soe-duration-tier-actions">
						<button type="button" class="button button-secondary" id="soe-add-duration-tier">
							<?php esc_html_e( 'Dauer hinzufügen', 'special-olympics-extension' ); ?>
						</button>
					</p>
				</div>

				<div class="soe-duration-settings-section soe-duration-rates-section">
					<h4 class="soe-duration-settings-subheading"><?php esc_html_e( 'Stundensätze', 'special-olympics-extension' ); ?></h4>
					<p class="soe-duration-tier-select-wrap">
						<label for="soe-duration-tier-select">
							<?php esc_html_e( 'Stufe', 'special-olympics-extension' ); ?>
							<select id="soe-duration-tier-select" class="soe-duration-tier-select">
								<?php foreach ( $duration_tiers as $tier ) : ?>
									<option value="<?php echo esc_attr( $tier['key'] ); ?>"><?php echo esc_html( $tier['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</p>
					<div id="soe-duration-rates-panels">
						<?php foreach ( $duration_tiers as $index => $tier ) : ?>
							<div
								class="soe-duration-rates-panel"
								data-tier-key="<?php echo esc_attr( $tier['key'] ); ?>"
								data-tier-index="<?php echo esc_attr( (string) $index ); ?>"
								<?php echo $index === 0 ? '' : ' hidden'; ?>
							>
								<?php soe_render_duration_tier_rates_table( $tier, $index, $hourly_rates ); ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<template id="soe-duration-tier-row-template">
				<div class="soe-duration-tier-row" data-tier-index="__INDEX__" data-tier-key="__KEY__">
					<input
						type="text"
						name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[duration_tiers][__INDEX__][label]"
						value=""
						class="regular-text soe-duration-label-input"
						placeholder="<?php esc_attr_e( 'Bezeichnung', 'special-olympics-extension' ); ?>"
						required
					/>
					<input
						type="hidden"
						name="<?php echo esc_attr( SOE_SETTINGS_OPTION ); ?>[duration_tiers][__INDEX__][key]"
						value="__KEY__"
						class="soe-duration-key-input"
					/>
					<button type="button" class="button button-link-delete soe-remove-duration-tier">
						<?php esc_html_e( 'Entfernen', 'special-olympics-extension' ); ?>
					</button>
				</div>
			</template>

			<template id="soe-duration-rates-panel-template">
				<div class="soe-duration-rates-panel" data-tier-key="__KEY__" data-tier-index="__INDEX__" hidden>
					<table class="widefat striped soe-duration-tier-rates">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Rolle', 'special-olympics-extension' ); ?></th>
								<th style="width: 100px;">CHF</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $slots as $slot ) : ?>
								<tr data-slot-prefix="<?php echo esc_attr( $slot['prefix'] ); ?>">
									<td><label><?php echo esc_html( $slot['label'] ); ?></label></td>
									<td>
										<input
											type="text"
											name=""
											value=""
											class="small-text soe-hourly-rate-input"
											data-slot-prefix="<?php echo esc_attr( $slot['prefix'] ); ?>"
										/>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</template>
		</td>
	</tr>
	<?php
}

/**
 * Enqueues scripts for the duration tiers UI on the payroll settings tab.
 *
 * @param string $hook_suffix Current admin page hook.
 * @return void
 */
function soe_duration_payroll_settings_enqueue( $hook_suffix ) {
	if ( $hook_suffix !== 'toplevel_page_soe-settings' ) {
		return;
	}
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
	if ( $tab !== 'payroll' ) {
		return;
	}
	wp_enqueue_script(
		'soe-settings-duration',
		plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/admin-settings-duration.js',
		array(),
		SOE_PLUGIN_VERSION,
		true
	);
	$slots = array();
	foreach ( soe_get_payroll_rate_slots() as $slot ) {
		$slots[] = array(
			'prefix' => $slot['prefix'],
			'label'  => $slot['label'],
		);
	}
	wp_localize_script(
		'soe-settings-duration',
		'soeDurationSettings',
		array(
			'optionName' => SOE_SETTINGS_OPTION,
			'slots'      => $slots,
			'i18n'       => array(
				'role' => __( 'Rolle', 'special-olympics-extension' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'soe_duration_payroll_settings_enqueue', 21 );
