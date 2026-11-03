<?php
/**
 * 日本語の見出し・ボタンを、言葉の切れ目で折り返す（スマホで「選べ／る」のように切れないように）。
 *
 * iPhone（Safari）は CSS の word-break:auto-phrase に対応していないため、サーバーで文節の切れ目に <wbr> を入れ、
 * その要素だけ word-break:keep-all にする。文節の切れ目は Google の BudouX（Apache License 2.0）の日本語モデル
 * （assets/data/budoux-ja.json）で判定する。
 *
 * 対象：サイトの表側のページの h1〜h4、ボタン（.btn など）、バッジ・チップ・小見出し。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** BudouX の日本語モデル。 */
function apprex_bx_model() {
	static $m = null;
	if ( null === $m ) {
		$f = APPREX_DIR . '/assets/data/budoux-ja.json';
		$m = is_readable( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$m = is_array( $m ) ? $m : array();
	}
	return $m;
}

/** BudouX の基本点（モデルの全部の値の合計の -1/2）。 */
function apprex_bx_base() {
	static $b = null;
	if ( null === $b ) {
		$sum = 0;
		foreach ( apprex_bx_model() as $g ) {
			$sum += array_sum( $g );
		}
		$b = -0.5 * $sum;
	}
	return $b;
}

/**
 * 文を文節に分ける（BudouX の parse と同じ計算）。
 *
 * @param string $s 文。
 * @return string[]
 */
function apprex_bx_parse( $s ) {
	$m = apprex_bx_model();
	if ( '' === $s || ! $m ) {
		return array( $s );
	}
	$c = preg_split( '//u', $s, -1, PREG_SPLIT_NO_EMPTY );
	$n = count( $c );
	// JavaScript の substring と同じ切り出し（範囲外は詰める）。
	$sub = function ( $a, $b ) use ( $c, $n ) {
		$a = max( 0, min( $n, $a ) );
		$b = max( 0, min( $n, $b ) );
		if ( $a > $b ) {
			list( $a, $b ) = array( $b, $a );
		}
		return implode( '', array_slice( $c, $a, $b - $a ) );
	};
	$g    = function ( $k, $str ) use ( $m ) {
		return isset( $m[ $k ][ $str ] ) ? $m[ $k ][ $str ] : 0;
	};
	$base = apprex_bx_base();
	$out  = array();
	$cur  = $c[0];
	for ( $i = 1; $i < $n; $i++ ) {
		$sc  = $base;
		$sc += $g( 'UW1', $sub( $i - 3, $i - 2 ) );
		$sc += $g( 'UW2', $sub( $i - 2, $i - 1 ) );
		$sc += $g( 'UW3', $sub( $i - 1, $i ) );
		$sc += $g( 'UW4', $sub( $i, $i + 1 ) );
		$sc += $g( 'UW5', $sub( $i + 1, $i + 2 ) );
		$sc += $g( 'UW6', $sub( $i + 2, $i + 3 ) );
		$sc += $g( 'BW1', $sub( $i - 2, $i ) );
		$sc += $g( 'BW2', $sub( $i - 1, $i + 1 ) );
		$sc += $g( 'BW3', $sub( $i, $i + 2 ) );
		$sc += $g( 'TW1', $sub( $i - 3, $i ) );
		$sc += $g( 'TW2', $sub( $i - 2, $i + 1 ) );
		$sc += $g( 'TW3', $sub( $i - 1, $i + 2 ) );
		$sc += $g( 'TW4', $sub( $i, $i + 3 ) );
		if ( $sc > 0 ) {
			$out[] = $cur;
			$cur   = '';
		}
		$cur .= $c[ $i ];
	}
	$out[] = $cur;
	return $out;
}

/** HTMLの中の文字の部分に、文節の切れ目の <wbr> を入れる（タグ・文字参照はそのまま）。 */
function apprex_bx_html( $html ) {
	if ( ! preg_match( '/[\x{3040}-\x{30ff}\x{4e00}-\x{9fff}]/u', $html ) ) {
		return $html; // 日本語がない。
	}
	$parts = preg_split( '/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	$out   = '';
	foreach ( $parts as $p ) {
		if ( '' === $p ) {
			continue;
		}
		if ( '<' === $p[0] || ! preg_match( '/[\x{3040}-\x{30ff}\x{4e00}-\x{9fff}]/u', $p ) ) {
			$out .= $p;
			continue;
		}
		// 文字参照（&amp; など）を壊さないよう、いったん1文字に置き換える。
		$ents = array();
		$t    = preg_replace_callback(
			'/&#?[a-z0-9]+;/i',
			function ( $mm ) use ( &$ents ) {
				$ents[] = $mm[0];
				return "\u{E000}";
			},
			$p
		);
		$t = implode( '<wbr>', apprex_bx_parse( $t ) );
		$k = 0;
		$t = preg_replace_callback(
			"/\u{E000}/u",
			function () use ( &$ents, &$k ) {
				return $ents[ $k++ ] ?? '';
			},
			$t
		);
		$out .= $t;
	}
	return $out;
}

/** 要素に class="bx" を足す。 */
function apprex_bx_add_class( $attrs ) {
	if ( preg_match( '/\sclass\s*=\s*"([^"]*)"/i', $attrs ) ) {
		return preg_replace( '/(\sclass\s*=\s*")([^"]*)"/i', '$1$2 bx"', $attrs, 1 );
	}
	return $attrs . ' class="bx"';
}

/** ページのHTMLを処理する。 */
function apprex_bx_filter( $html ) {
	if ( strlen( $html ) < 200 || false === stripos( $html, '<html' ) ) {
		return $html;
	}
	$orig = $html;
	// 見出し。
	$html = preg_replace_callback(
		'#<(h[1-4])(\s[^>]*)?>(.*?)</\1>#su',
		function ( $m ) {
			if ( false !== strpos( $m[3], '<wbr' ) || preg_match( '#<(h[1-6]|div|p|ul|table)\b#i', $m[3] ) ) {
				return $m[0];
			}
			return '<' . $m[1] . apprex_bx_add_class( $m[2] ?? '' ) . '>' . apprex_bx_html( $m[3] ) . '</' . $m[1] . '>';
		},
		$html
	);
	if ( null === $html ) {
		return $orig; // 正規表現が失敗したら、何もしないで返す。
	}
	$orig = $html;
	// ボタン・バッジ・チップ・小見出し。
	$html = preg_replace_callback(
		'#<(a|button|span|b|strong|p)(\s[^>]*class="[^"]*\b(btn|sp-btn|badge-top|chip|eyebrow|ncat|ap-line-btn|lead-title)\b[^"]*"[^>]*)>([^<]*(?:<(?!/?\1\b)[^<]*)*)</\1>#su',
		function ( $m ) {
			if ( false !== strpos( $m[4], '<wbr' ) || preg_match( '#<(div|p|ul|table|h[1-6])\b#i', $m[4] ) ) {
				return $m[0];
			}
			return '<' . $m[1] . apprex_bx_add_class( $m[2] ) . '>' . apprex_bx_html( $m[4] ) . '</' . $m[1] . '>';
		},
		$html
	);
	return null === $html ? $orig : $html;
}

add_action(
	'template_redirect',
	function () {
		if ( is_admin() || is_feed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_customize_preview() ) {
			return;
		}
		if ( ! apply_filters( 'apprex_bx_enabled', true ) ) {
			return;
		}
		ob_start( 'apprex_bx_filter' );
	},
	0
);

/* =========================================================================
 * スマホの見え方（assets/css/mobile.css を最後に読み込む・表のカード化）
 * ====================================================================== */
add_action(
	'wp_enqueue_scripts',
	function () {
		$deps = array();
		foreach ( array( 'apprex-style', 'apprex-renewal', 'apprex-renewal-pages' ) as $h ) {
			if ( wp_style_is( $h, 'enqueued' ) || wp_style_is( $h, 'registered' ) ) {
				$deps[] = $h;
			}
		}
		wp_enqueue_style( 'apprex-mobile', APPREX_URI . '/assets/css/mobile.css', $deps, APPREX_VERSION );
	},
	99
);

/* 表：見出しの文字を各マスに付けて、スマホでは1行ずつのカードにする。 */
add_action(
	'wp_footer',
	function () {
		?>
<script>(function(){try{document.querySelectorAll('.rn .tbl table').forEach(function(t){var h=[].map.call(t.querySelectorAll('thead th'),function(x){return x.textContent.trim();});if(h.length<3)return;t.querySelectorAll('tbody tr').forEach(function(r){[].forEach.call(r.children,function(c,i){if(h[i])c.setAttribute('data-label',h[i]);});});var w=t.closest('.tbl');w.classList.add('mfit');if(h.length>=4)w.classList.add('mgrid');});}catch(e){}})();</script>
		<?php
	},
	98
);
