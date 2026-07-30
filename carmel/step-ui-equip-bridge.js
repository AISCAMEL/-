/* =========================================================
   carmel / 在庫STEP UI 装備ブリッジ
   ---------------------------------------------------------
   目的:
     在庫編集画面の「車両入力 STEP UI」ステップ2で選んだ装備を、
     実際に保存される ACF の装備チェックボックス
     （基本装備 / オーディオ / カーナビ・TV / シート・内装 /
       ドア・外装 / 安全装備）にリアルタイムで連動させる。

   背景（バグ）:
     ステップ2の .cs-equip-check は表示専用の独立チェックボックスで、
     ACF フィールドと連動していなかった。そのため装備を選んでも
     通常の「更新」では保存されず「チェックが入らない」状態だった。
     このスクリプトを入れると、選んだ瞬間に対応する ACF の
     チェックが入り、通常の「更新」ボタンで確実に保存される。

   導入方法（どちらか）:
     A) WPCode →「+ スニペットを追加」→ コードタイプ「JavaScript Snippet」
        挿入位置「管理画面のみ(Admin Only)」で本ファイルの中身を貼り付け、有効化。
     B) 既存の「車両入力 STEP UI」を出力している PHP/スニペットの
        末尾 <script> 内に、IIFE の中身を追記。

   ※ jQuery 前提（在庫編集画面では読み込み済み）。
   ========================================================= */
(function ($) {
    'use strict';

    /* ステップ2の装備値 → ACF フィールドの data-name（1択チェックボックス）
       ※ ACF 側に対応項目がある装備のみマッピング。
         対応項目が無いもの（Apple CarPlay / Android Auto / レーンアシスト /
         ドライブレコーダー / ベンチレーションシート / 革シート / パノラマルーフ /
         プッシュスタート / LEDヘッドライト / フォグランプ / アダプティブライト /
         純正ナビ / 社外ナビ / 車歴系）は意図的に未マッピング。 */
    var EQUIP_MAP = {
        // ナビ・AV
        'フルセグTV': 'nav5',
        'DVD再生': 'dvd',
        'Bluetooth': 'bluetooth',
        // 安全装備
        '自動ブレーキ': 'shoutotu',
        'クルーズコントロール': 'controll',
        'アダプティブクルーズ': 'controll',
        'バックカメラ': 'kamera3',
        '360度カメラ': 'kamera4',
        'コーナーセンサー': 'sensar',
        // 快適装備
        'シートヒーター': 'heater',
        '電動シート': 'seat',
        'サンルーフ': 'sunroof',
        'パワーバックドア': 'gate',
        '電動スライドドア': 'door',
        // キー
        'スマートキー': 'smartkey',
        // ホイール
        'アルミホイール': 'almi',
        '純正アルミ': 'almi',
        'ローダウン': 'down',
        'エアロパーツ': 'earo',
        // ETC
        'ETC': 'etc',
        'ETC2.0': 'etc'
    };

    // data-name → ステップ2の値（逆引き、初期反映用。最初に一致したものを採用）
    var REVERSE_MAP = (function () {
        var r = {};
        Object.keys(EQUIP_MAP).forEach(function (label) {
            var name = EQUIP_MAP[label];
            if (!(name in r)) r[name] = label;
        });
        return r;
    })();

    // 指定 ACF チェックボックスフィールドをオン/オフ
    function tickAcf(dataName, on) {
        var $field = $('.acf-field[data-name="' + dataName + '"]');
        if (!$field.length) return;
        var $cb = $field.find('input[type="checkbox"]').first();
        if (!$cb.length) return;
        if ($cb.prop('checked') === on) return; // 変化なしなら何もしない
        $cb.prop('checked', on);
        // ACF の見た目（選択ハイライト）も合わせる
        $cb.closest('label').toggleClass('selected', on);
        $cb.trigger('change');
    }

    // ステップ2チェック1件 → ACF へ反映
    function syncOne($chk) {
        var name = EQUIP_MAP[$.trim($chk.val())];
        if (name) tickAcf(name, $chk.is(':checked'));
    }

    // ステップ2の全チェックを ACF へ反映
    function syncAll() {
        $('.cs-equip-check').each(function () {
            syncOne($(this));
        });
    }

    // 既存レコードを開いたとき、ACF の状態をステップ2へ初期反映
    function prefillStepFromAcf() {
        Object.keys(REVERSE_MAP).forEach(function (dataName) {
            var $field = $('.acf-field[data-name="' + dataName + '"]');
            if (!$field.length) return;
            var checked = $field.find('input[type="checkbox"]').first().is(':checked');
            if (!checked) return;
            var label = REVERSE_MAP[dataName];
            $('.cs-equip-check').filter(function () {
                return $.trim($(this).val()) === label;
            }).prop('checked', true);
        });
    }

    $(function () {
        // STEP UI が存在しなければ何もしない
        if (!document.getElementById('carmel_step_ui')) return;

        // 初期反映（既存の装備をステップ2に表示）
        prefillStepFromAcf();

        // ステップ2のチェック変更で即連動
        $(document).on('change', '.cs-equip-check', function () {
            syncOne($(this));
        });

        // ステップ移動（次へ/戻る/ナビボタン）時にまとめて連動（取りこぼし防止）
        $(document).on('click', '.cs-nav-btn, .cs-btn-next, .cs-btn-back', function () {
            setTimeout(syncAll, 60);
        });

        // 「全リセット」でステップ2が空になったら ACF 側も対応分をオフ
        $(document).on('click', '#cs-reset-all-btn', function () {
            setTimeout(function () {
                Object.keys(REVERSE_MAP).forEach(function (dataName) {
                    tickAcf(dataName, false);
                });
            }, 80);
        });
    });
})(jQuery);
