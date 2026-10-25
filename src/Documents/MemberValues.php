<?php
/**
 * Placeholder values for a member.
 *
 * @package MemberDocuments
 */

namespace MemberDocuments\Documents;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the {{placeholder}} values available to every template.
 */
final class MemberValues {

	/**
	 * Values for a user. Filterable, so a site can add its own (for example a
	 * subscription plan or renewal date).
	 *
	 * @param \WP_User $user Member.
	 * @return array<string, scalar>
	 */
	public function for_user( \WP_User $user ): array {
		$values = array(
			'member_name'  => '' !== trim( $user->display_name ) ? $user->display_name : $user->user_login,
			'member_email' => $user->user_email,
			'member_id'    => $user->ID,
			'date'         => wp_date( get_option( 'date_format' ) ),
			'site_name'    => get_bloginfo( 'name' ),
		);

		/**
		 * Filters the placeholder values for a member.
		 *
		 * @param array<string, scalar> $values Values keyed by placeholder name.
		 * @param \WP_User              $user   Member.
		 */
		return (array) apply_filters( 'mdocs_member_values', $values, $user );
	}

	/**
	 * Names of the built-in placeholders, for the admin help text.
	 *
	 * @return string[]
	 */
	public static function names(): array {
		return array( 'member_name', 'member_email', 'member_id', 'date', 'site_name' );
	}
}
