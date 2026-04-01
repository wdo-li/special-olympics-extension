<?php
/**
 * "Athlet*innen": Top-level admin menu for non-admins.
 * Replaces the standard "Mitglieder" list with a custom page showing only posts created by the current user
 * (excluding the post that represents the current user themselves / Mein Account).
 *
 * @package Special_Olympics_Extension
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'soe_meine_athleten_menu', 9999 );

/**
 * For non-admins: hide "Mitglieder". Only Ansprechpersonen see "Athlet*innen" page.
 */
function soe_meine_athleten_menu() {
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}
	// Hide the standard "Mitglieder" CPT menu for all non-admins.
	remove_menu_page( 'edit.php?post_type=mitglied' );

	// Only Ansprechpersonen see the "Athlet*innen" page.
	$user = wp_get_current_user();
	if ( ! in_array( 'ansprechperson', (array) $user->roles, true ) ) {
		return;
	}
	add_menu_page(
		__( 'Athlet*innen', 'special-olympics-extension' ),
		__( 'Athlet*innen', 'special-olympics-extension' ),
		'edit_mitglieds',
		'soe-meine-athleten',
		'soe_render_meine_athleten_page',
		'dashicons-groups',
		null
	);
}

/**
 * Renders the "Athlet*innen" admin page: list of mitglied posts by current user (excluding own profile), no delete, edit link, add button.
 * Only accessible by Ansprechpersonen (and Admins).
 */
function soe_render_meine_athleten_page() {
	$user = wp_get_current_user();
	$is_ansprechperson = in_array( 'ansprechperson', (array) $user->roles, true );
	if ( ! current_user_can( 'manage_options' ) && ! $is_ansprechperson ) {
		wp_die( esc_html__( 'Du hast keine Berechtigung für diese Seite.', 'special-olympics-extension' ) );
	}
	$user_id = get_current_user_id();
	$posts   = get_posts( array(
		'post_type'      => 'mitglied',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
		'author'         => $user_id,
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => array(
			'relation' => 'OR',
			array( 'key' => SOE_MEMBER_STATUS_META, 'value' => SOE_MEMBER_STATUS_ARCHIVED, 'compare' => '!=' ),
			array( 'key' => SOE_MEMBER_STATUS_META, 'compare' => 'NOT EXISTS' ),
		),
	) );
	// Exclude the post that represents the current user (Mein Account – user_id = current user).
	$my_mitglied_id = function_exists( 'soe_get_current_user_mitglied_id' ) ? soe_get_current_user_mitglied_id() : 0;
	$posts         = array_filter( $posts, function ( $post ) use ( $my_mitglied_id ) {
		return (int) $post->ID !== (int) $my_mitglied_id;
	} );
	$posts         = array_values( $posts );
	$add_url       = admin_url( 'post-new.php?post_type=mitglied' );
	$can_create    = current_user_can( 'publish_mitglieds' );
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Athlet*innen', 'special-olympics-extension' ); ?></h1>
		<?php if ( $can_create ) : ?>
			<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Athlet*in hinzufügen', 'special-olympics-extension' ); ?></a>
		<?php endif; ?>
		<hr class="wp-header-end" />

		<?php if ( empty( $posts ) ) : ?>
			<p><?php esc_html_e( 'Noch keine Athlet*innen angelegt.', 'special-olympics-extension' ); ?></p>
			<?php if ( $can_create ) : ?>
				<p><a href="<?php echo esc_url( $add_url ); ?>" class="button button-primary"><?php esc_html_e( 'Athlet*in hinzufügen', 'special-olympics-extension' ); ?></a></p>
			<?php endif; ?>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th scope="col" class="column-title column-primary"><?php esc_html_e( 'Name', 'special-olympics-extension' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Aktionen', 'special-olympics-extension' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $posts as $post ) : ?>
						<?php
						$edit_url = get_edit_post_link( $post->ID, 'raw' );
						if ( ! $edit_url || ! current_user_can( 'edit_post', $post->ID ) ) {
							continue;
						}
						?>
						<tr>
							<td class="column-title column-primary">
								<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $post->post_title ?: __( '(Ohne Titel)', 'special-olympics-extension' ) ); ?></a></strong>
							</td>
							<td>
								<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Bearbeiten', 'special-olympics-extension' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
