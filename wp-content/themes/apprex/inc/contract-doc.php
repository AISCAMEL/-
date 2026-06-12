<?php
/**
 * 契約書（書面）＋電子契約連携（マネーフォワード クラウド契約：リンク＋ステータス方式）。
 *
 * - 契約書テンプレート（管理画面で条項を編集、{{差し込み}}対応）
 * - 契約レコードから契約書を自動生成 → A4印刷最適化ページで表示（ブラウザでPDF保存）
 * - マネーフォワード クラウド契約と「リンク＋ステータス」で連携
 *     締結ページURL / 締結ステータス / 署名済みPDFのURL / 締結日 を契約に記録
 * - 会員マイページに「契約書」セクション（プレビュー・締結ボタン・署名済みPDF）
 * - 締結済みに切り替えた時、会員へ確認メールを送信
 *
 * APIキー不要。締結自体はマネーフォワード側で行い、本テーマはその入口と記録を担います。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 締結ステータス
 * ---------------------------------------------------------------------- */

/** マネーフォワード契約の締結ステータス（値 => ラベル）。 */
function apprex_mf_statuses() {
	return array(
		'none'     => '未送付',
		'sent'     => '送付済（締結待ち）',
		'signed'   => '締結済',
		'rejected' => '却下／取消',
	);
}

/** 締結ステータスのラベル。 */
function apprex_mf_status_label( $key ) {
	$s = apprex_mf_statuses();
	return isset( $s[ $key ] ) ? $s[ $key ] : '未送付';
}

/* -------------------------------------------------------------------------
 * 契約書テンプレート
 * ---------------------------------------------------------------------- */

/** 差し込みプレースホルダの説明（テンプレート編集画面で表示）。 */
function apprex_contract_placeholders() {
	return array(
		'{{contract_id}}' => '契約ID',
		'{{name}}'        => 'お名前',
		'{{company}}'     => '会社名',
		'{{email}}'       => 'メール',
		'{{member_type}}' => '会員種別',
		'{{service}}'     => 'サービス',
		'{{plan}}'        => 'プラン',
		'{{monthly}}'     => '月額（数字のみ）',
		'{{monthly_yen}}' => '月額（¥表記）',
		'{{start}}'       => '契約開始日',
		'{{term}}'        => '契約年数',
		'{{renewal}}'     => '次回更新日',
		'{{provider}}'      => '事業者情報（甲・複数行）',
		'{{provider_name}}' => '事業者名（甲・会社名のみ）',
		'{{today}}'         => '本日の日付',
	);
}

/** 事業者情報（甲）の既定値。 */
function apprex_contract_provider_default() {
	$name = get_bloginfo( 'name' );
	return "会社名：{$name}\n所在地：\n代表者：";
}

/** 契約書テンプレートの既定値（アプリワン サービス利用規約）。 */
function apprex_contract_template_default() {
	return <<<'HTML'
<h1>契約書</h1>

<p>{{provider_name}}（以下「甲」という）と {{company}}{{name}}（以下「乙」という）は甲が提供する「{{service}}」におけるアプリ作成サービスの乙の利用に関して、以下の通り契約を締結する。</p>

<p>本利用規約（以下「本規約」といいます。）には、本サービス（用語の定義は第２条によります。以下同じ。）の提供条件及び甲と乙の皆様との間の権利関係が定められています。本サービスの利用に際しては、本規約の全文をお読みいただいたうえで、本規約に同意いただく必要があります。</p>

<h2>第１条（適用）</h2>
<p>本規約は、本サービスの提供条件及び本サービスの利用に関する甲と乙との権利義務関係を定めることを目的とし、乙と甲との間の本サービスの利用に関わる一切の関係に適用されます。</p>

<h2>第２条（定義）</h2>
<p>1.「サービス利用契約」とは、本規約及び甲と乙との間で締結する、本サービスの利用契約を意味します。<br>
2.「知的財産権」とは、著作権、特許権、実用新案権、意匠権、商標権その他の知的財産権（それらの権利を取得し、又はそれらの権利につき登録等を出願する権利を含みます。）を意味します。<br>
3.「送信データ」とは、乙が本サービスを利用して作成するアプリケーション（以下「アプリ」といいます。）に使用するために、乙が甲に対して送信するコンテンツ（文章、画像、動画その他データを含みますが、これらに限りません。）を意味します。<br>
4.「乙」とは、第３条（登録）各項に基づいて本サービスの利用者としての登録がなされた個人又は法人を意味します。<br>
5.「本サービス」とは、甲が提供する「アプリワン」という名称のサービス（モバイルデバイス向けアプリ作成支援サービスとし、ただし、理由の如何を問わずサービスの名称又は内容が変更された場合には、当該変更後のサービスを含みます。）を意味します。<br>
6.「アプリ利用者」とは、乙が本サービスを利用して作成したアプリを、AppStore、GooglePlayその他のモバイルプラットフォーム（以下「登録プラットフォーム」といいます。）に登録した後、当該登録プラットフォームから取得したうえで利用する者を意味します。</p>

<h2>第３条（登録）</h2>
<p>１.本サービスの利用を希望する者（以下「登録希望者」といいます。）は、本規約を遵守することに同意し、かつ甲の定める一定の情報（以下「登録事項」といいます。）を甲が定める方法で甲に提供することにより、甲に対し、本サービスの利用の登録を申請することができます。<br>
２.甲は、甲の基準に従って、第１項に基づいて登録申請を行った登録希望者（以下「登録申請者」といいます。）の登録の可否を判断し、甲が登録を認める場合にはその旨を、認証メールにて登録申請者に通知します。登録申請者の乙としての登録は、甲が本項の通知を行ったことをもって完了したものとします。<br>
３.前項に定める登録の完了時に、サービス利用契約が乙と甲との間に成立し、乙は本サービスを本規約に従い利用することができるようになります。<br>
４.甲は、登録申請者が、以下の各号のいずれかの事由に該当する場合は、登録及び再登録を拒否することがあり、またその理由について一切開示義務を負いません。</p>
<p>(1)甲に提供した登録事項の全部又は一部につき虚偽、誤記又は記載漏れがあった場合<br>
(2)未成年者、成年被後見人、被保佐人又は被補助人のいずれかであり、法定代理人、後見人、保佐人又は補助人の同意等を得ていない場合<br>
(3)反社会的勢力等（暴力団、暴力団員、右翼団体、反社会的勢力、その他これに準ずる者を意味します。以下同じ。）である、又は資金提供その他を通じて反社会的勢力等の維持、運営若しくは経営に協力若しくは関与する等反社会的勢力等との何らかの交流若しくは関与を行っていると甲が判断した場合<br>
(4)登録希望者が過去、甲との契約に違反した者又はその関係者であると甲が判断した場合<br>
(5)第１０条に定める措置（登録抹消等）を受けたことがある場合<br>
(6)その他、甲が登録を適当でないと判断した場合</p>

<h2>第４条（登録事項の変更）</h2>
<p>乙は、登録事項に変更があった場合、甲の定める方法により当該変更事項を遅滞なく甲に通知するものとします。</p>

<h2>第５条（パスワード及びユーザーIDの管理）</h2>
<p>１.乙は、自己の責任において、本サービスに関するパスワード及びユーザーIDを適切に管理及び保管するものとし、これを第三者に利用させ、または貸与、譲渡、名義変更、売買等をしてはならないものとします。<br>
２.パスワード及びユーザーIDの管理不十分、使用上の過誤、第三者の使用等によって生じた損害に関する責任は乙が負うものとし、甲は一切の責任を負いません。</p>

<h2>第６条（料金、支払方法、契約期間）</h2>
<p>１.乙は、本サービスの利用の対価として、別途甲が定めた利用料金を、甲が指定する支払い方法により甲に支払うものとします。</p>
<p>初期費用は ○○円（税別）とする。<br>
制作費は ○○万円（税別）とする。<br>
月額利用料は {{monthly_yen}}（税別）とする。<br>
初期費用 ○○円（税別）は △△年△△月△△日までに支払うものとする。<br>
月額利用料 {{monthly_yen}}（税別）は 毎月△△日正午までに支払うものとする。<br>
支払い済みの初期費用、制作費及び月額管理費はいかなる理由であっても返金はできません。</p>
<p>2.乙が本サービスを利用し作成したアプリを、登録プラットフォームに登録申請したにもかかわらず、当該アプリが登録プラットフォームの審査等に合格せず、登録を拒絶された場合でも、甲は本サービスの利用料金の返金義務を負いません。<br>
3.契約開始日は {{start}}（申込日の翌月の一日）とし、申込日から契約開始日までは無料でご利用できる。</p>

<h2>第７条（禁止事項）</h2>
<p>乙は、本サービスの利用にあたり、以下の各号のいずれかに該当する行為又は該当すると甲が判断する行為をしてはなりません。</p>
<p>(1)法令に違反する行為又は犯罪行為に関連する行為並びに法令に違反するおそれのある行為<br>
(2)甲、本サービスの他の利用者又はその他の第三者に対する詐欺又は脅迫行為<br>
(3)公序良俗に反する行為<br>
(4)甲、本サービスの他の利用者又はその他の第三者の知的財産権、肖像権、プライバシー権、名誉、その他の権利又は利益を侵害する行為<br>
(5)本サービスを通じ、以下に該当し、または該当すると甲が判断する情報を甲又は本サービスの他の利用者に送信すること<br>
　ア　過度に暴力的又は残虐な表現を含む情報<br>
　イ　コンピューターウィルスその他の有害なコンピュータープログラムを含む情報<br>
　ウ　甲、本サービスの他の利用者又はその他の第三者の名誉又は信用を毀損する表現を含む情報<br>
　エ　わいせつな表現を含む情報<br>
　オ　差別を助長する表現を含む情報<br>
　カ　自殺、自傷行為を助長する表現を含む情報<br>
　キ　薬物の不適切な利用を助長する表現を含む情報<br>
　ク　反社会的な表現を含む情報<br>
　ケ　チェーンメール等の第三者への情報の拡散を求める情報<br>
　コ　他人に不快感を与える表現を含む情報<br>
(6)本サービスのネットワーク又はシステム等に過度な負担をかける行為<br>
(7)本サービスの運営を妨害するおそれのある行為<br>
(8)甲のネットワーク又はシステム等に不正にアクセスし、または不正なアクセスを試みる行為<br>
(9)第三者に成りすます行為<br>
(10)本サービスの他の利用者のID又はパスワードを利用する行為<br>
(11)甲が事前に許諾しない本サービス上での宣伝、公告、勧誘又は営業行為<br>
(12)本サービスの他の利用者の情報の収集<br>
(13)甲、本サービスの他の利用者又はその他の第三者に不利益、損害、不快感を与える行為<br>
(14)甲ウェブサイト上で掲載する本サービス利用に関するルールに抵触する行為<br>
(15)反社会的勢力等への利益供与<br>
(16)前各号の行為を直接又は間接に惹起し又は容易にする行為<br>
(17)本サービスを、勝手に修正、変更、改変、リバースエンジニアリング、逆コンパイル、逆アセンブル等する行為<br>
(18)その他、甲が不適切と判断する行為</p>

<h2>第８条（本サービスの停止等）</h2>
<p>１.甲は、以下のいずれかに該当する場合には、乙に事前に通知することなく、本サービスの全部又は一部の提供を停止又は中断することができるものとします。<br>
(1)本サービスに係るコンピューターシステムの点検又は保守作業を緊急に行う場合<br>
(2)コンピューター、通信回線等が事故により停止した場合<br>
(3)地震、落雷、火災、風水害、停電及び天災地変などの不可抗力により本サービスの運営ができなくなった場合<br>
(4)その他、甲が停止又は中断を必要と判断した場合<br>
２.事由のいかんを問わず、甲は、本条に基づき甲が行った措置に基づき乙に生じた損害について一切の責任を負いません。</p>

<h2>第９条（権利帰属）</h2>
<p>１.甲ウェブサイト及び本サービスに関するものは全て甲又は甲にライセンスを許諾している者に帰属しており、本規約に基づく本サービスの利用許諾は、甲ウェブサイト又は本サービスに関する甲又は甲にライセンスを許諾している者の知的財産権の使用許諾を意味するものではありません。<br>
２.乙は、送信データについて、自らが送信することについての適法な権利を有していること及び送信データが第三者の権利を侵害していないことについて、甲に対し表明し、保証するものとします。乙がこれに違反した場合、故意過失を問わず、当該違反から生じる紛争（クレーム、訴訟を含むがこれに限られない）について、乙は自己の費用と負担で解決するものとします。<br>
３.乙が本サービスを利用して登録編集した文章、映像、動画等（以下「本件コンテンツ」といいます。）の著作権については乙その他既存の権利者に留保されるものとします。ただし、甲は、本サービスの提供終了後であったとしても、本件コンテンツを含む本サービスの内容を、取扱事例として、甲のウェブサイト、情報誌その他各種媒体（電磁的方法であるかを問わない）に対して、無償で掲載することができ、乙はこれを許諾するものとします。<br>
４.前項に定める乙が本サービスを利用して登録編集した本件コンテンツについての著作権を除き、本サービス及び本サービスに関連する一切の情報（絵柄、レイアウト、ユーザーインターフェイス、動作、クリックボタンの配列、画面構成、ページ構成、プログラムなど本サービスの仕様を構成する全ての要素を含むがこれに限られない）についての著作権及びその他知的財産権は全て甲又は甲に利用を許諾した権利者に帰属し、乙は無断で複製、譲渡、貸与、翻訳、改変、転載、公衆送信（送信可能化を含む）等をしてはならないものとします。</p>

<h2>第１０条（登録抹消等）</h2>
<p>１.甲は、乙が、以下の各号のいずれかの事由に該当する場合は、事前に通知又は催告することなく、送信データの全部又は一部を削除し若しくは当該乙について本サービスの利用を一時的に停止し又は乙としての登録を抹消若しくはサービス利用契約を解除することができます。<br>
(1)本規約のいずれかの条項に違反した場合<br>
(2)登録事項に虚偽の事実があることが判明した場合<br>
(3)本サービスの利用料金などの支払債務の履行を遅滞し又は支払を拒否した場合<br>
(4)支払停止若しくは支払不能となり又は破産手続開始、民事再生手続開始、会社更生手続開始、特別清算開始若しくはこれらに類する手続の開始の申立があった場合<br>
(5)甲からの問合せその他の回答を求める連絡に対して１ヶ月以上応答がない場合<br>
(6)第３条第４項各号に該当する場合<br>
(7)その他、甲が本サービスの利用、乙としての登録又はサービス利用契約の継続を適当でないと判断した場合<br>
２.前項各号のいずれかの事由に該当した場合、乙は甲に対して負っている債務の一切について当然に期限の利益を失い、直ちに甲に対して全ての債務の支払を行わなければなりません。<br>
３.本条に基づき登録抹消等がされた場合、次条４項が適用されるものとし、甲は、本条に基づき甲が行った行為により乙に生じた損害について一切の責任を負いません。また、アプリ利用者について生じた損害についても同様とします。</p>

<h2>第１１条（退会）</h2>
<p>１.乙は、甲所定の方法で甲に通知することにより、本サービスから退会し、自己の乙としての登録を抹消することができます。<br>
2.退会にあたり、甲に負っている債務がある場合は、乙は甲に対して負っている債務の一切について当然に期限の利益を失い、直ちに甲に対して全ての債務の支払をおこなわなければなりません。<br>
3.退会後の利用者情報の取り扱いについては、第１５条の規定に従うものとします。<br>
4.乙が退会した後、本サービスを利用して作成したアプリ及びアプリ作成に関する情報等は、甲の定める一定期間の経過後に使用できなくなります。入稿したデータが全て消去されますので、乙の判断において、本件コンテンツについて退会申出に先立ってバックアップを行ってください。</p>

<h2>第１２条（本サービスの内容の変更、終了－解除）</h2>
<p>１.甲は、甲の都合により、本サービスの内容を変更し、又は提供を終了することができます。甲が本サービスの提供を終了する場合、甲は乙に事前に通知するものとします。<br>
２.甲は、本条に基づき甲が行った措置に基づき乙に生じた損害について一切の責任を負いません。</p>

<h2>第１３条（保証の否認及び免責）</h2>
<p>１.甲は、本サービスが乙の特定の目的に適合すること、期待する機能、商品的価値、正確性、有用性を有すること（本サービスを使用することにより、登録プラットフォームへの登録審査に合格することを含むがこれに限られません）、乙による本サービスの利用が乙に適用のある法令又は業界団体の内部規制等に適合すること及び不具合が生じないことについて、何ら保証するものではありません。<br>
２.甲は、甲による本サービスの提供の中断、停止、終了、利用不能又は変更、乙が本サービスに送信したメッセージ又は情報の削除又は消失、乙の登録の抹消、本サービスの利用による登録データの消失又は機器の故障若しくは損傷、その他本サービスに関して乙が被った損害（営業上の利益の損失、業務の中断、営業情報の喪失、登録プラットフォームに関わるデータの喪失など乙の情報の消失及び毀損などの損害を含む。以下「ユーザー損害」といいます。）につき、賠償する責任を一切負わないものとします。<br>
３.何らかの理由により甲が責任を負う場合でも、甲は、乙の損害につき、過去２ヶ月間に乙が甲に支払った対価の金額を超えて賠償する責任を負わないものとし、また、付随的損害、間接的損害、特別損害、将来の損害及び逸失利益にかかる損害については、賠償する責任を負わないものとします。<br>
４.本サービス又は甲ウェブサイトに関連して乙と他の乙、アプリ利用者又は第三者との間において生じた取引、連絡、紛争等について、甲は一切責任を負いません。<br>
５.乙が本サービスを利用して作成したアプリを登録プラットフォームに登録の申請をする場合、乙は甲に対して、登録プラットフォームの申請に必要な情報を提供するものとします。なお、登録プラットフォームの申請に必要な情報に、クレジットカード情報等が含まれた場合、これに起因する乙、その他の第三者に対して生じた一切の損害について、甲は一切の責任を負わないものとします。</p>

<h2>第１４条（秘密保持）</h2>
<p>乙は、本サービスの利用又は本規約に関して知り得た甲の秘密情報を第三者に提供、開示、漏洩してはならないものとします。</p>

<h2>第１５条（利用者情報の取り扱い）</h2>
<p>１.甲による乙の利用者情報のうち、個人情報取り扱いについては、甲の個人情報保護方針の定めによるものとし、乙はこの個人情報保護方針に従って甲が乙の利用者情報を取り扱うことについて同意するものとします。<br>
２.甲は、統計的な分析及びより乙のニーズにあったサービスを提供する目的で、乙が甲に提供したアプリのジャンルに関する情報及び乙の属性に関する情報等を収集することがあります。収集したこれらの情報等は、甲の裁量で、乙を特定しない方法で利用及び公開することができる（甲が目的達成のため必要と判断した第三者と共有することを含みます）ものとし、乙はこれに異議を唱えないものとします。<br>
３.甲は、統計的な分析、より乙及びアプリ利用者のニーズにあったサービスを提供するため及び広告に利用する目的で、アプリ利用者の端末情報、当該アプリの利用履歴（アプリ利用者が閲覧したページの情報、滞在時間等）及びログデータ等のアプリ利用者の個人情報以外の情報（以下「サービス情報」といいます。）を収集することがあります。収集したサービス情報は、甲の裁量で、乙及びアプリ利用者を特定しない方法で利用及び甲が目的達成のため必要と判断した第三者と共有することができるものとします。<br>
４.甲は、登録プラットフォームへ申請するのに必要な情報を、本サービスの乙への情報提供（アプリダウンロード数及びダウンロード傾向等の分析結果など）をするための目的の達成に必要な範囲内で、業務委託先、調査会社等（以下「業務委託先等」といいます。）へ提供することができるものとし、乙はこれに異議を唱えないものとします。<br>
５.甲は、前項の場合において、業務委託先等からサービス情報が漏洩した場合において、甲の故意又は重過失による場合を除き一切責任を負いません。</p>

<h2>第１６条（本規約等の変更）</h2>
<p>甲は、本規約を変更することができるものとします。甲は、本規約を変更した場合には、乙に当該変更内容を1ヶ月前に通知するものとし、当該変更内容の通知後、乙が本サービスを利用した場合又は甲の定める期間内に登録抹消の手続を取らなかった場合には、乙は、本規約の変更に同意したものとみなします。</p>

<h2>第１７条（連絡、通知）</h2>
<p>本サービスに関する問合せその他乙から甲に対する連絡又は通知、及び本規約の変更に関する通知その他甲から乙に対する連絡又は通知は、甲の定める方法により行うものとします。</p>

<h2>第１８条（利用契約上の地位の譲渡等）</h2>
<p>１.乙は、甲の書面による事前の承諾なく、利用契約上の地位又は本規約に基づく権利若しくは義務につき、第三者に対し、譲渡、移転、担保設定、その他の処分をすることはできません。<br>
２.甲は本サービスにかかる事業を他社に譲渡した場合には、当該事業譲渡に伴い利用契約上の地位、本規約に基づく権利の移転及び義務並びに乙の登録事項その他の顧客情報を当該事業譲渡の譲受人に譲渡することができるものとし、乙は、かかる譲渡につき本項において予め同意したものとみなします。なお、本項に定める事業譲渡には、通常の事業譲渡のみならず、会社分割その他事業が移転するあらゆる場合を含むものとします。</p>

<h2>第１９条（分離可能性）</h2>
<p>本規約のいずれかの条項又はその一部が、消費者契約法その他の法令等により無効又は執行不能と判断された場合であっても、本規約の残りの規定及び一部が無効又は執行不能と判断された規定の残存部分は、継続して完全に効力を有するものとします。</p>

<h2>第２０条（準拠法及び管轄裁判所）</h2>
<p>１.本規約及びサービス利用契約の準拠法は日本法とします。なお、本サービスにおいて物品の売買が発生する場合であっても、国際物品売買契約に関する国際連合条約の適用を排除することに同意します。<br>
２.本規約又はサービス利用契約に起因し、又は関連する一切の紛争については、東京地方裁判所又は東京簡易裁判所を第一審の専属的合意管轄裁判所とします。</p>

<p style="text-align:center;margin-top:32px;">{{today}}</p>

<div class="apprex-doc-sign">
<p>&lt;甲&gt;<br>
{{provider}}　印</p>
<p>&lt;乙&gt;<br>
会社名：{{company}}<br>
住　所：<br>
代表者：{{name}}　印</p>
</div>
HTML;
}


/* -------------------------------------------------------------------------
 * テンプレート編集ページ（契約メニュー配下）
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=apprex_contract',
		'契約書テンプレート',
		'契約書テンプレート',
		'manage_options',
		'apprex-contract-template',
		'apprex_contract_template_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'apprex_contract_doc', 'apprex_contract_doc_title', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_contract_doc', 'apprex_contract_provider', array( 'sanitize_callback' => 'wp_kses_post' ) );
	register_setting( 'apprex_contract_doc', 'apprex_contract_template', array( 'sanitize_callback' => 'wp_kses_post' ) );
} );

/** 契約書テンプレート編集画面。 */
function apprex_contract_template_page() {
	$title    = get_option( 'apprex_contract_doc_title', '契約書' );
	$provider = get_option( 'apprex_contract_provider', '' );
	$tpl      = get_option( 'apprex_contract_template', '' );
	if ( '' === $provider ) {
		$provider = apprex_contract_provider_default();
	}
	if ( '' === $tpl ) {
		$tpl = apprex_contract_template_default();
	}
	?>
	<div class="wrap">
		<h1>契約書テンプレート</h1>
		<p>各契約の情報を差し込んで契約書を自動生成します。締結は「マネーフォワード クラウド契約」で行い、締結ページURL・状況・署名済みPDFを各契約に記録します。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_contract_doc' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="apprex_contract_doc_title">タイトル</label></th>
					<td><input type="text" id="apprex_contract_doc_title" name="apprex_contract_doc_title" class="regular-text" value="<?php echo esc_attr( $title ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_contract_provider">事業者情報（甲）</label></th>
					<td>
						<textarea id="apprex_contract_provider" name="apprex_contract_provider" rows="3" class="large-text"><?php echo esc_textarea( $provider ); ?></textarea>
						<p class="description">自社（甲）の名称・住所・代表者など。テンプレ内の <code>{{provider}}</code> に差し込まれます。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_contract_template">契約書本文</label></th>
					<td>
						<textarea id="apprex_contract_template" name="apprex_contract_template" rows="22" class="large-text code"><?php echo esc_textarea( $tpl ); ?></textarea>
						<p class="description">HTML（見出し <code>&lt;h2&gt;</code>、段落 <code>&lt;p&gt;</code>、箇条書き <code>&lt;ul&gt;&lt;li&gt;</code> 等）が使えます。</p>
						<p class="description"><strong>差し込みタグ：</strong>
							<?php
							$chips = array();
							foreach ( apprex_contract_placeholders() as $tag => $desc ) {
								$chips[] = '<code>' . esc_html( $tag ) . '</code>＝' . esc_html( $desc );
							}
							echo wp_kses_post( implode( '　／　', $chips ) );
							?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( '保存' ); ?>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * 契約書の生成・表示
 * ---------------------------------------------------------------------- */

/** 契約書表示用URL（フロント、権限チェック付きエンドポイント）。 */
function apprex_contract_doc_url( $contract_id ) {
	return add_query_arg( 'apprex_doc', (int) $contract_id, home_url( '/' ) );
}

/** この契約書を閲覧してよいか（管理者 or 本人）。 */
function apprex_can_view_contract_doc( $contract_id ) {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$u = wp_get_current_user();
	if ( (int) get_post_meta( $contract_id, 'apprex_c_user_id', true ) === (int) $u->ID ) {
		return true;
	}
	$email = (string) get_post_meta( $contract_id, 'apprex_c_email', true );
	return $email && strtolower( $email ) === strtolower( $u->user_email );
}

/**
 * テンプレートに契約情報を差し込んで本文HTMLを返す。
 *
 * @param int $contract_id 契約ID。
 * @return string
 */
function apprex_contract_doc_body( $contract_id ) {
	$m = function ( $k ) use ( $contract_id ) {
		return (string) get_post_meta( $contract_id, $k, true );
	};
	$tpl = get_option( 'apprex_contract_template', '' );
	if ( '' === $tpl ) {
		$tpl = apprex_contract_template_default();
	}
	$provider = get_option( 'apprex_contract_provider', '' );
	if ( '' === $provider ) {
		$provider = apprex_contract_provider_default();
	}
	// 甲の会社名のみ（「会社名：◯◯」行から抽出、無ければ先頭行 / サイト名）。
	$provider_name = '';
	foreach ( preg_split( '/\r\n|\r|\n/', $provider ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		if ( preg_match( '/会社名[：:]\s*(.+)$/u', $line, $mm ) ) {
			$provider_name = trim( $mm[1] );
			break;
		}
		if ( '' === $provider_name ) {
			$provider_name = $line;
		}
	}
	if ( '' === $provider_name ) {
		$provider_name = get_bloginfo( 'name' );
	}

	$mtype   = $m( 'apprex_c_member_type' );
	$monthly = (int) $m( 'apprex_c_monthly' );
	$map     = array(
		'{{contract_id}}' => (string) $contract_id,
		'{{name}}'        => $m( 'apprex_c_name' ),
		'{{company}}'     => $m( 'apprex_c_company' ),
		'{{email}}'       => $m( 'apprex_c_email' ),
		'{{member_type}}' => $mtype && function_exists( 'apprex_member_type_label' ) ? apprex_member_type_label( $mtype ) : '',
		'{{service}}'     => $m( 'apprex_c_service' ),
		'{{plan}}'        => $m( 'apprex_c_plan' ),
		'{{monthly}}'     => (string) $monthly,
		'{{monthly_yen}}' => '¥' . number_format( $monthly ),
		'{{start}}'       => $m( 'apprex_c_start' ),
		'{{term}}'        => $m( 'apprex_c_term' ) ? $m( 'apprex_c_term' ) . '年' : '',
		'{{renewal}}'     => $m( 'apprex_c_renewal' ),
		'{{provider}}'      => nl2br( $provider ),
		'{{provider_name}}' => $provider_name,
		'{{today}}'         => wp_date( 'Y年n月j日' ),
	);
	return strtr( $tpl, $map );
}

/** 契約書のスタンドアロンHTML（A4印刷最適化）を出力。 */
function apprex_render_contract_document( $contract_id ) {
	$title  = get_option( 'apprex_contract_doc_title', '契約書' );
	$body   = apprex_contract_doc_body( $contract_id );
	$status = get_post_meta( $contract_id, 'apprex_c_mf_status', true );
	$signed = 'signed' === $status;
	$sdate  = get_post_meta( $contract_id, 'apprex_c_mf_signed_at', true );
	header( 'Content-Type: text/html; charset=UTF-8' );
	?>
<!doctype html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		*{box-sizing:border-box}
		body{font-family:"Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif;color:#111827;line-height:1.9;margin:0;background:#f3f4f6}
		.apprex-doc-toolbar{position:sticky;top:0;background:#111827;color:#fff;padding:10px 16px;display:flex;justify-content:space-between;align-items:center;gap:10px}
		.apprex-doc-toolbar button{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 18px;font-size:14px;cursor:pointer}
		.apprex-doc-sheet{max-width:820px;margin:18px auto;background:#fff;padding:48px 56px;box-shadow:0 1px 6px rgba(0,0,0,.12)}
		.apprex-doc-sheet h1{font-size:24px;text-align:center;margin:0 0 28px;letter-spacing:.1em}
		.apprex-doc-sheet h2{font-size:16px;margin:24px 0 6px;border-left:4px solid #2563eb;padding-left:10px}
		.apprex-doc-sheet ul{margin:6px 0 6px 1.2em}
		.apprex-doc-sign{margin-top:40px;padding-top:18px;border-top:1px solid #d1d5db}
		.apprex-doc-stamp{display:inline-block;margin-top:10px;color:#16a34a;border:2px solid #16a34a;border-radius:8px;padding:6px 14px;font-weight:bold;transform:rotate(-4deg)}
		@media print{body{background:#fff}.apprex-doc-toolbar{display:none}.apprex-doc-sheet{box-shadow:none;margin:0;max-width:none;padding:0}@page{margin:18mm}}
	</style>
</head>
<body>
	<div class="apprex-doc-toolbar">
		<span>契約書プレビュー（「PDFで保存」から保存できます）</span>
		<button type="button" onclick="window.print()">🖨 印刷 / PDFで保存</button>
	</div>
	<div class="apprex-doc-sheet">
		<?php echo wp_kses_post( $body ); ?>
		<?php if ( $signed ) : ?>
			<p class="apprex-doc-stamp">電子締結済<?php echo $sdate ? '（' . esc_html( $sdate ) . '）' : ''; ?></p>
		<?php endif; ?>
	</div>
</body>
</html>
	<?php
}

/** フロントの契約書エンドポイント（?apprex_doc=契約ID）。 */
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['apprex_doc'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$id = absint( $_GET['apprex_doc'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $id || 'apprex_contract' !== get_post_type( $id ) ) {
		wp_die( '契約書が見つかりません。' );
	}
	if ( ! apprex_can_view_contract_doc( $id ) ) {
		auth_redirect();
	}
	apprex_render_contract_document( $id );
	exit;
} );

/* -------------------------------------------------------------------------
 * 保存：マネーフォワード連携フィールド＋締結時の確認メール
 * ---------------------------------------------------------------------- */
add_action( 'save_post_apprex_contract', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['apprex_contract_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprex_contract_nonce'] ) ), 'apprex_contract_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$old_status = get_post_meta( $post_id, 'apprex_c_mf_status', true );
	$new_status = isset( $_POST['apprex_c_mf_status'] ) ? sanitize_text_field( wp_unslash( $_POST['apprex_c_mf_status'] ) ) : 'none';
	if ( ! array_key_exists( $new_status, apprex_mf_statuses() ) ) {
		$new_status = 'none';
	}
	update_post_meta( $post_id, 'apprex_c_mf_status', $new_status );
	update_post_meta( $post_id, 'apprex_c_mf_url', isset( $_POST['apprex_c_mf_url'] ) ? esc_url_raw( wp_unslash( $_POST['apprex_c_mf_url'] ) ) : '' );
	update_post_meta( $post_id, 'apprex_c_mf_signed_pdf', isset( $_POST['apprex_c_mf_signed_pdf'] ) ? esc_url_raw( wp_unslash( $_POST['apprex_c_mf_signed_pdf'] ) ) : '' );
	update_post_meta( $post_id, 'apprex_c_mf_signed_at', isset( $_POST['apprex_c_mf_signed_at'] ) ? sanitize_text_field( wp_unslash( $_POST['apprex_c_mf_signed_at'] ) ) : '' );

	// 締結済みへ切り替わった時だけ、会員へ確認メールを一度送る。
	if ( 'signed' === $new_status && 'signed' !== $old_status ) {
		apprex_notify_contract_signed( $post_id );
	}
} );

/** 締結完了の確認メールを会員へ送信。 */
function apprex_notify_contract_signed( $contract_id ) {
	$email = get_post_meta( $contract_id, 'apprex_c_email', true );
	if ( ! is_email( $email ) ) {
		return;
	}
	$name   = get_post_meta( $contract_id, 'apprex_c_name', true );
	$mypage = function_exists( 'apprex_mypage_url' ) ? apprex_mypage_url() : home_url( '/mypage/' );
	$pdf    = get_post_meta( $contract_id, 'apprex_c_mf_signed_pdf', true );

	$body  = "{$name} 様\n\n契約の電子締結が完了しました。ありがとうございます。\n\n";
	$body .= "マイページから契約書をご確認いただけます：\n{$mypage}\n";
	if ( $pdf ) {
		$body .= "\n署名済み契約書（PDF）：\n{$pdf}\n";
	}
	$body .= "\n今後ともよろしくお願いいたします。\n";

	$subject = '【APPREX】契約締結完了のお知らせ';
	$html    = function_exists( 'apprex_render_email' )
		? apprex_render_email( $subject, $body, array( 'heading' => '契約締結完了のお知らせ' ) )
		: nl2br( esc_html( $body ) );
	wp_mail( $email, $subject, $html, function_exists( 'apprex_mail_headers' ) ? apprex_mail_headers() : array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/* -------------------------------------------------------------------------
 * 一覧に「締結」カラムを追加
 * ---------------------------------------------------------------------- */
add_filter( 'manage_apprex_contract_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'cstatus' === $k ) {
			$new['mfstatus'] = __( '締結', 'apprex' );
		}
	}
	return $new;
} );

add_action( 'manage_apprex_contract_posts_custom_column', function ( $col, $post_id ) {
	if ( 'mfstatus' === $col ) {
		echo esc_html( apprex_mf_status_label( get_post_meta( $post_id, 'apprex_c_mf_status', true ) ) );
	}
}, 10, 2 );
