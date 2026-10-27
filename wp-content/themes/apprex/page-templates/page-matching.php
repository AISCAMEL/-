<?php
/**
 * Template Name: マッチングアプリ開発
 *
 * マッチングアプリ開発（開発費・保守は個別見積り、期間3ヶ月〜）の詳細ページ。
 * 見積り依頼は既存のフォーム基盤（REST /inquiry）で受け付ける。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<div class="rn">
<section class="hero mhero">
  <div class="crumb"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">トップ</a> ／ オリジナル開発 ／ マッチングアプリ開発</div>
  <div class="wrap">
    <div>
      <span class="badge-top rise">オリジナル開発 ／ iOS・Android同時開発</span>
      <h1 class="rise d1">マッチングアプリを、<br><em>格安・3ヶ月〜</em>で。</h1>
      <div class="price-big rise d2"><b>無料</b><span>でお見積り</span><small>開発費は、作りたい機能に合わせて個別にお見積りします。APPREXの開発基盤を使うので、一般的なフルスクラッチ開発より費用と期間を抑えられます。</small></div>
      <p class="lead rise d2">スキル・ビジネス・人材・フリマなど、人と人、会社と会社をつなぐアプリを開発します。APPREXの開発基盤とFlutterを使うので、管理画面までそろったアプリを低価格で作れます。</p>
      <div class="chips rise d3"><span class="chip">管理画面つき</span><span class="chip">iOS・Android同時</span><span class="chip">公開後の保守まで対応</span><span class="chip">ソース買取も可</span></div>
      <div class="ctas rise d3">
        <a class="btn btn--cta btn--lg pulse" href="#lead">無料で見積りを依頼する</a>
        <a class="btn btn--line btn--lg" href="<?php echo esc_url( apprex_studio_url( 'matching' ) ); ?>">AIで試作を作る（無料）</a>
      </div>
      <p class="rise d3" style="margin:12px 0 0;font-size:.85rem;color:#cbd7f2">作りたいサービスを書くだけで、AIが触れる試作と仕様定義書を作ります。<a style="color:#ffcf6e" href="<?php echo esc_url( apprex_demo_url( 'sample', array( 'v' => 'orig', 't' => 'matching' ) ) ); ?>" target="_blank" rel="noopener">サンプルを触ってみる</a></p>
    </div>
    <div style="min-width:0">
      <div class="mstage" aria-hidden="true">
        <div class="mph"><span class="notch"></span><div class="scr">
          <div class="m-top">SkillLink<span>3 新着</span></div>
          <div class="m-card"><div class="m-photo">佐藤 さん ／ Webデザイナー</div><div class="m-body"><b>経験7年・即日対応</b><div class="m-tags"><i>LP制作</i><i>Figma</i><i>バナー</i><i>★4.9</i></div></div></div>
          <div class="m-acts"><b>×</b><b class="like">♥</b></div>
        </div></div>
        <div class="m-chat"><b>マッチングしました</b>佐藤さんとメッセージできます</div>
      </div>
    </div>
  </div>
</section>

<section class="sec" id="cost">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">Cost</span><h2>一般的な開発との違い</h2><p>同じ機能のマッチングアプリでも、作り方で費用と期間が大きく変わります。</p></div>
    <div class="tbl">
      <table class="cmp2">
        <thead><tr><th>比べること</th><th>一般的なフルスクラッチ開発</th><th class="app">APPREX</th></tr></thead>
        <tbody>
          <tr><td><b>開発費</b></td><td>数百万〜数千万円</td><td class="app">開発基盤を使って抑えた個別見積り</td></tr>
          <tr><td><b>公開までの期間</b></td><td>半年〜1年程度</td><td class="app">3ヶ月〜</td></tr>
          <tr><td><b>iOS・Android</b></td><td>2本を別々に開発することも</td><td class="app">Flutterで同時に開発</td></tr>
          <tr><td><b>管理画面</b></td><td>別途見積りになりやすい</td><td class="app">会員・取引・売上の管理画面つき</td></tr>
          <tr><td><b>公開後の保守</b></td><td>開発費の年10〜20%程度が目安</td><td class="app">月額の保守プラン（個別見積り）</td></tr>
          <tr><td><b>ソースコード</b></td><td>契約による</td><td class="app">買い取りプランあり</td></tr>
        </tbody>
      </table>
    </div>
    <p class="cap">一般的な開発の費用・期間は目安です。機能や規模によって変わります。</p>
  </div>
</section>

<section class="sec alt" id="types">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">Types</span><h2>こんなマッチングアプリが作れます</h2></div>
    <div class="mtypes">
      <div class="mt"><b>スキルマッチング</b><small>仕事を頼みたい人と、スキルを持つ人をつなぐ</small><span class="ex">例：デザイン・家事代行・家庭教師</span></div>
      <div class="mt"><b>ビジネスマッチング</b><small>発注したい会社と受注したい会社をつなぐ</small><span class="ex">例：外注先探し・業務提携・商談</span></div>
      <div class="mt"><b>人材・求人</b><small>働きたい人と、人を探している会社をつなぐ</small><span class="ex">例：スキマバイト・副業・専門職</span></div>
      <div class="mt"><b>フリマ・売買</b><small>個人どうし、事業者どうしで物を売り買い</small><span class="ex">例：中古品・ハンドメイド・業務用品</span></div>
      <div class="mt"><b>講師・習い事</b><small>教えたい人と習いたい人をつなぐ</small><span class="ex">例：オンラインレッスン・スポーツ指導</span></div>
      <div class="mt"><b>スペース・シェア</b><small>空いている場所や物を貸し借りする</small><span class="ex">例：貸し会議室・駐車場・機材</span></div>
      <div class="mt"><b>地域・コミュニティ</b><small>同じ地域や趣味の人どうしをつなぐ</small><span class="ex">例：地域の助け合い・サークル</span></div>
      <div class="mt warn"><b>恋活・婚活</b><small>異性との出会いを目的とするサービス</small><span class="ex">届出が必要です（下の「届出の注意」）</span></div>
    </div>
  </div>
</section>

<section class="sec" id="func">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">Features</span><h2>マッチングに必要な機能</h2><p>基本のプランで作れる機能の例です。内容に合わせて組み合わせます。</p></div>
    <div class="funcs">
      <div class="fn"><i>01</i><div><b>会員登録・ログイン</b><small>メール、Apple・Googleアカウントで登録</small></div></div>
      <div class="fn"><i>02</i><div><b>プロフィール</b><small>写真・自己紹介・スキル・実績・料金</small></div></div>
      <div class="fn"><i>03</i><div><b>検索・絞り込み</b><small>地域・カテゴリ・料金・評価で探す</small></div></div>
      <div class="fn"><i>04</i><div><b>いいね・応募・依頼</b><small>気になる相手にアプローチ</small></div></div>
      <div class="fn"><i>05</i><div><b>メッセージ</b><small>マッチングした相手とやり取り。画像も送れる</small></div></div>
      <div class="fn"><i>06</i><div><b>プッシュ通知</b><small>新着・メッセージ・マッチングをお知らせ</small></div></div>
      <div class="fn"><i>07</i><div><b>決済・手数料</b><small>報酬の支払いと、運営手数料の自動計算</small></div></div>
      <div class="fn"><i>08</i><div><b>レビュー・評価</b><small>取引後にお互いを評価して安心感を高める</small></div></div>
      <div class="fn"><i>09</i><div><b>本人確認・通報・ブロック</b><small>身分証の確認と、トラブルを防ぐ仕組み</small></div></div>
      <div class="fn adm"><i>管</i><div><b>会員管理</b><small>会員の検索・停止・本人確認の承認</small></div></div>
      <div class="fn adm"><i>管</i><div><b>取引・売上管理</b><small>取引の状況、手数料、売上の集計</small></div></div>
      <div class="fn adm"><i>管</i><div><b>お知らせ・問い合わせ</b><small>全員へのお知らせ配信と、問い合わせ対応</small></div></div>
    </div>
  </div>
</section>

<section class="sec alt" id="price">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">Price</span><h2>料金</h2></div>
    <div class="mprice">
      <div class="mp-main">
        <h3>マッチングアプリ開発プラン</h3>
        <div class="mp-row"><div><small>開発費</small><b>個別見積り</b></div><div><small>月額保守</small><b>個別見積り</b></div></div>
        <ul><li>iOS・Androidアプリ（Flutterで同時開発）</li><li>管理画面（会員・取引・売上・お知らせ）</li><li>デザイン、テスト、ストア申請の代行</li><li>保守：OS更新への対応、不具合の修正、サーバー管理</li><li>開発費のお支払いは着手時と納品時の2回に分けられます</li></ul>
      </div>
      <div class="mp-opt">
        <h3>追加できる機能の例</h3>
        <div class="tbl" style="border:0"><table><tbody>
          <tr><td>ビデオ通話</td><td class="r">個別見積り</td></tr>
          <tr><td>外部サービスでの本人確認（eKYC）</td><td class="r">個別見積り</td></tr>
          <tr><td>AIによるおすすめ相手の表示</td><td class="r">個別見積り</td></tr>
          <tr><td>サブスク（月額会員）</td><td class="r">個別見積り</td></tr>
          <tr><td>Web版（ブラウザで使える版）</td><td class="r">個別見積り</td></tr>
          <tr><td>ソースコード買い取り</td><td class="r">個別見積り</td></tr>
        </tbody></table></div>
        <p class="cap">アプリストアの登録費用も、お見積りに含めてご案内します。</p>
      </div>
    </div>
  </div>
</section>

<section class="sec" id="why">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">Why low cost</span><h2>格安で作れる理由</h2></div>
    <div class="why">
      <div class="wcard"><div class="k">REASON 1</div><h3>マッチングの基盤がある</h3><p>会員登録、プロフィール、メッセージ、通知、管理画面など、マッチングに必ず要る部分はAPPREXの基盤を使います。ゼロから作らない分、費用と期間が下がります。</p></div>
      <div class="wcard"><div class="k">REASON 2</div><h3>iOS・Androidを一度に</h3><p>Flutterで1つのコードから両方のアプリを作ります。2本分の開発費がかかりません。</p></div>
      <div class="wcard"><div class="k">REASON 3</div><h3>最初は小さく公開</h3><p>取引に必要な最低限の機能で先に公開し、利用者の声を見ながら機能を追加します。使われない機能にお金をかけません。</p></div>
    </div>
    <div class="fig2" style="margin-top:28px"><img src="<?php echo esc_url( apprex_rn_img( '05_case-matching.jpg' ) ); ?>" alt="マッチングアプリの画面を確認する開発チーム" width="1376" height="768" loading="lazy" decoding="async"></div>
  </div>
</section>

<section class="sec alt" id="tips">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">For success</span><h2>マッチングアプリを成功させる3つのポイント</h2><p>アプリを作ることより、使う人を集めて続けてもらうことのほうが大切です。設計の段階から一緒に考えます。</p></div>
    <div class="tips">
      <div class="tip"><div class="k">POINT 1</div><h3>片方の利用者を先に集める</h3><p>相手が見つからないアプリはすぐに使われなくなります。依頼する側か受ける側のどちらかを、公開前から集めておきます。</p></div>
      <div class="tip"><div class="k">POINT 2</div><h3>取引に必要な機能から始める</h3><p>登録・検索・メッセージ・決済など、取引が成立する最低限の機能で公開し、反応を見て広げます。</p></div>
      <div class="tip"><div class="k">POINT 3</div><h3>運営の体制を決めておく</h3><p>問い合わせ対応、不正利用の監視、トラブル時の対応。管理画面で運営しやすい形に作ります。</p></div>
    </div>
  </div>
</section>

<section class="sec" id="law">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">Legal</span><h2>始める前に確認したい届出・ルール</h2><p>マッチングの種類によっては、届出や許可が必要です。必要な手続きは専門家にもご確認ください。</p></div>
    <div class="law">
      <div class="lw"><b>恋活・婚活のアプリ</b><p>異性との出会いを目的とするサービスは「インターネット異性紹介事業」として、公安委員会への届出が必要です。年齢確認も義務です。</p></div>
      <div class="lw"><b>中古品の売買を仲介するアプリ</b><p>扱う品物や仕組みによっては、古物商の許可が関係します。</p></div>
      <div class="lw"><b>お金を預かる仕組み</b><p>利用者どうしの代金を運営が一時的に預かる場合は、決済の仕組みによって資金決済法などの確認が必要です。</p></div>
    </div>
    <p class="cap" style="text-align:center">あわせて、利用規約・プライバシーポリシー・特定商取引法に基づく表記も用意します。</p>
  </div>
</section>

<section class="sec alt" id="mflow">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">Process</span><h2>ご依頼から公開までの流れ</h2></div>
    <ol class="mflow">
      <li><i>1</i><div><b>AIスタジオで仕様を作る</b><small>無料会員登録して、マッチングの種類と機能を選ぶと仕様書と概算ができます</small></div><em>無料</em></li>
      <li><i>2</i><div><b>開発を相談・オンラインミーティング</b><small>仕様書を見ながら、集客や運営の方法まで一緒に整理します</small></div><em>60分</em></li>
      <li class="pay"><i>3</i><div><b>お見積り・ご入金（着手金）</b><small>開発費は着手時と納品時の2回払いにできます</small></div><em>—</em></li>
      <li><i>4</i><div><b>要件定義・画面設計</b><small>画面の流れを固め、動く試作で確認します</small></div><em>1〜2週間</em></li>
      <li><i>5</i><div><b>開発・テスト</b><small>テスト版をお手元のスマホで操作していただきます</small></div><em>3週間〜</em></li>
      <li><i>6</i><div><b>ストア申請・公開</b><small>App Store・Google Play の審査対応を代行します</small></div><em>1〜2週間</em></li>
      <li><i>7</i><div><b>運営・改善</b><small>月額保守で、OS更新や機能追加に対応します</small></div><em>公開後</em></li>
    </ol>
  </div>
</section>

<section class="band" id="lead">
  <div class="wrap">
    <div>
      <h2>マッチングアプリの見積りを、無料で依頼する</h2>
      <ul><li>2営業日以内に概算をお送りします</li><li>アイデアの段階でも大丈夫です</li><li>秘密保持契約（NDA）にも対応します</li><li>しつこい営業はしません</li></ul>
    </div>
    <form class="formcard" id="mForm" data-apprex-form data-type="contact" data-compose>
      <h3>見積りを依頼する（無料）</h3>
      <div class="fgrid"><input type="text" name="name" autocomplete="name" placeholder="お名前" aria-label="お名前" required><input type="text" name="company" autocomplete="organization" placeholder="会社名" aria-label="会社名"></div>
      <div class="fgrid"><input type="email" name="email" autocomplete="email" inputmode="email" placeholder="メールアドレス" aria-label="メールアドレス" required>
        <select data-label="マッチングの種類" aria-label="マッチングの種類"><option value="">マッチングの種類</option><option>スキル</option><option>ビジネス</option><option>人材・求人</option><option>フリマ・売買</option><option>講師・習い事</option><option>スペース・シェア</option><option>地域・コミュニティ</option><option>恋活・婚活</option><option>その他・未定</option></select></div>
      <div class="fgrid"><select data-label="公開したい時期" aria-label="公開したい時期"><option value="">公開したい時期</option><option>3ヶ月以内</option><option>半年以内</option><option>1年以内</option><option>未定</option></select>
        <select data-label="ご予算" aria-label="ご予算"><option value="">ご予算</option><option>〜300万円</option><option>300〜500万円</option><option>500〜1,000万円</option><option>1,000万円以上</option><option>相談したい</option></select></div>
      <textarea name="message" hidden>【マッチングアプリ開発の見積り依頼】</textarea>
      <button class="btn btn--cta btn--lg" type="submit">見積りを依頼する</button>
      <p class="fnote">送信後、AIスタジオで仕様を作ると、より正確な見積りをお出しできます。<?php if ( get_privacy_policy_url() ) : ?><br>送信により<a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">プライバシーポリシー</a>に同意したものとします。<?php endif; ?></p>
      <div class="apprex-form__result" role="status" hidden></div>
    </form>
  </div>
</section>

<section class="sec" id="faq">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">FAQ</span><h2>よくあるご質問</h2></div>
    <div class="faq">
      <details open><summary>費用はどのくらいかかりますか？</summary><p>登録・プロフィール・検索・メッセージ・通知・管理画面など、マッチングの基本の機能から作れます。機能の数と内容に合わせて個別にお見積りします。AIスタジオで試作と仕様を作っていただくと、より早く正確なお見積りをお出しできます（無料）。</p></details>
      <details><summary>どのくらいで公開できますか？</summary><p>3ヶ月〜が目安です。機能の数とストアの審査期間によって変わります。ミーティングでスケジュールをお出しします。</p></details>
      <details><summary>アイデアだけでも相談できますか？</summary><p>はい。AIスタジオで機能を選ぶと仕様書のたたき台ができるので、それをもとにミーティングで一緒に固めていきます。</p></details>
      <details><summary>公開後に機能を追加できますか？</summary><p>できます。小さく公開して、利用者の反応を見ながら追加していく進め方をおすすめしています。</p></details>
      <details><summary>ソースコードはもらえますか？</summary><p>ソースコード買い取りのプランがあります。将来、自社で改修を続けたい場合にお選びください。</p></details>
      <details><summary>集客も手伝ってもらえますか？</summary><p>ストア掲載文の最適化や、公開前の利用者集めの方法をご提案します。広告運用などはご相談ください。</p></details>
    </div>
  </div>
</section>

<section class="fcta">
  <div class="wrap">
    <h2>まずは仕様と概算を、無料で。</h2>
    <p>AIスタジオで機能を選ぶだけ。マッチングアプリの仕様書と概算がすぐにできます。</p>
    <div class="ctas">
      <a class="btn btn--cta btn--lg" href="<?php echo esc_url( apprex_studio_url() ); ?>">AIスタジオで仕様を作る</a>
      <a class="btn btn--line btn--lg" href="#lead">見積りを依頼する</a>
    </div>
  </div>
</section>
</div>
<?php
get_footer();
