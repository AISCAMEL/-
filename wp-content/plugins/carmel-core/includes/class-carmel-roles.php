<?php
/**
 * Defines the 4-tier role hierarchy and capability matrix.
 *
 * Note on row-level scoping: WordPress capabilities cannot express
 * "own store only". Capabilities here grant the *type* of action a role
 * may perform; restricting an owner/staff to their own store's records is
 * enforced at runtime by query filters (see Carmel_Access_Control and the
 * forthcoming portal query layer), not by caps.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Roles {

	/** Role slugs => display names (LEVEL 1-4). */
	const ROLES = array(
		'hq_admin'    => '本部管理者',
		'store_owner' => '加盟店オーナー',
		'store_staff' => '加盟店スタッフ',
		'customer'    => 'ユーザー',
	);

	/**
	 * Cross-cutting custom capabilities (beyond per-CPT caps).
	 *
	 * @return string[]
	 */
	public static function custom_caps() {
		return array(
			'carmel_change_deal_status', // ステータス変更
			'carmel_screening',          // 信販審査結果入力（本部のみ）
			'carmel_send_contract',      // 契約送付（マネーフォワード契約・本部のみ）
			'carmel_view_reports',       // 売上レポート
			'carmel_manage_stores',      // 加盟店管理（本部のみ）
			'carmel_manage_staff',       // スタッフ管理（オーナー：自店）
			'carmel_view_own',           // 自分の案件閲覧（顧客）
		);
	}

	/**
	 * Operational CPT cap_types that owner & staff manage.
	 *
	 * @return string[]
	 */
	private static function operational_cap_types() {
		return array(
			'carmel_deal',
			'carmel_vehicle',
			'carmel_document',
			'carmel_support',
			'carmel_inspection',
			'carmel_insurance',
		);
	}

	/**
	 * Primitive capabilities WordPress expects for a capability_type plural.
	 *
	 * @param string $plural e.g. 'carmel_deals'
	 * @return array<string,bool>
	 */
	public static function full_caps( $plural ) {
		$caps = array(
			"edit_{$plural}",
			"edit_others_{$plural}",
			"edit_private_{$plural}",
			"edit_published_{$plural}",
			"publish_{$plural}",
			"read_private_{$plural}",
			"delete_{$plural}",
			"delete_private_{$plural}",
			"delete_published_{$plural}",
			"delete_others_{$plural}",
		);
		return array_fill_keys( $caps, true );
	}

	/**
	 * Read-only caps for a capability_type plural.
	 *
	 * @param string $plural
	 * @return array<string,bool>
	 */
	public static function read_caps( $plural ) {
		return array(
			"edit_{$plural}"         => true, // 閲覧+自身分の編集（行フィルターで制限）
			"read_private_{$plural}" => true,
		);
	}

	/**
	 * Create/refresh all roles and their capabilities. Idempotent.
	 */
	public static function add_roles_and_caps() {
		$defs = Carmel_Post_Types::definitions();

		// --- Build each role's capability map ---------------------------------
		$caps = array(
			'hq_admin'    => array( 'read' => true ),
			'store_owner' => array( 'read' => true ),
			'store_staff' => array( 'read' => true ),
			'customer'    => array( 'read' => true ),
		);

		foreach ( $defs as $slug => $def ) {
			$plural = $def['cap_type'] . 's';

			// 本部：全CPTフルアクセス
			$caps['hq_admin'] += self::full_caps( $plural );

			// オーナー/スタッフ：業務系CPTを操作
			if ( in_array( $def['cap_type'], self::operational_cap_types(), true ) ) {
				$caps['store_owner'] += self::full_caps( $plural );
				$caps['store_staff'] += self::full_caps( $plural );
			}

			// 加盟店CPT：オーナーは自店を編集、スタッフは閲覧のみ
			if ( 'carmel_store' === $def['cap_type'] ) {
				$caps['store_owner'] += self::read_caps( $plural );
			}

			// 返済CPT：オーナー/スタッフは閲覧、本部はフル（上で付与済み）
			if ( 'carmel_repayment' === $def['cap_type'] ) {
				$caps['store_owner'] += self::read_caps( $plural );
				$caps['store_staff'] += self::read_caps( $plural );
			}
		}

		// --- Cross-cutting custom caps ---------------------------------------
		$caps['hq_admin'] += array(
			'carmel_change_deal_status' => true,
			'carmel_screening'          => true,
			'carmel_send_contract'      => true,
			'carmel_view_reports'       => true,
			'carmel_manage_stores'      => true,
			'carmel_manage_staff'       => true,
		);
		$caps['store_owner'] += array(
			'carmel_change_deal_status' => true,
			'carmel_view_reports'       => true, // 自店のみ（行フィルター）
			'carmel_manage_staff'       => true, // 自店のみ
		);
		$caps['store_staff'] += array(
			'carmel_change_deal_status' => true,
		);
		$caps['customer'] += array(
			'carmel_view_own' => true,
		);

		// 画像添付（コミュニティ等）のためのメディアアップロード権限。
		foreach ( array( 'hq_admin', 'store_owner', 'store_staff', 'customer' ) as $role ) {
			$caps[ $role ]['upload_files'] = true;
		}

		// --- Persist roles ----------------------------------------------------
		foreach ( self::ROLES as $slug => $name ) {
			remove_role( $slug ); // ensure caps are refreshed on re-activation
			add_role( $slug, $name, $caps[ $slug ] );
		}

		// 本部のフルCPTキャパを既定の administrator にも付与（運用補助）
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( $caps['hq_admin'] as $cap => $grant ) {
				$admin->add_cap( $cap );
			}
		}
	}

	/**
	 * Remove roles and strip caps from administrator (used on uninstall).
	 */
	public static function remove_roles_and_caps() {
		foreach ( array_keys( self::ROLES ) as $slug ) {
			remove_role( $slug );
		}
		$admin = get_role( 'administrator' );
		if ( ! $admin ) {
			return;
		}
		foreach ( Carmel_Post_Types::definitions() as $def ) {
			foreach ( self::full_caps( $def['cap_type'] . 's' ) as $cap => $grant ) {
				$admin->remove_cap( $cap );
			}
		}
		foreach ( self::custom_caps() as $cap ) {
			$admin->remove_cap( $cap );
		}
	}
}
