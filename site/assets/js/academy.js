/* ============================================================
   BUYMO 加盟店アカデミー（動画＋テキスト学習）
   - コース/レッスンは COURSES を編集（video は YouTube等の埋め込みURL or 空）
   - 進捗は localStorage に保存。partner-academy.html（ハブ）と
     partner-course.html（受講画面）の両方で利用。
   ============================================================ */
window.Academy = (function () {
  'use strict';
  var PKEY = 'buymo_academy_progress';

  var COURSES = [
    { id: 'basic', icon: '🎓', title: '買取ビジネスの基礎', desc: 'BUYMOのモデルと全体像を理解する', lessons: [
      { t: 'BUYMOのビジネスモデル', video: '', text: '在庫を持たない買取・即時売却モデル。中間コストを抑え、相場より高い査定を実現します。加盟店は「集客は本部、対応は店舗」で役割分担します。' },
      { t: '取引の全体像（申込→入金）', video: '', text: '申込→無料出張査定→契約→名義変更等の手続き→入金。各ステップで対応履歴を残し、看板ボードで進捗管理します。' },
      { t: 'コンプライアンス（古物営業法）', video: '', text: '古物商許可・本人確認・取引記録の保存が必須。お客様の個人情報はプライバシーポリシーに従い適切に扱います。' }
    ]},
    { id: 'appraisal', icon: '🔍', title: '査定スキル', desc: '車両を正しく見極める', lessons: [
      { t: '外装・内装のチェック', video: '', text: 'キズ・へこみ・修復歴・内装の状態を確認。減点ポイントと加点ポイントを把握します。' },
      { t: '機関・電装の確認', video: '', text: 'エンジン・ミッション・電装・足回り。不動車/事故車の見立てと部品価値の考え方。' },
      { t: '相場の調べ方', video: '', text: '査定シミュレーター・オークション実績から相場を把握。需要の高い装備・カラーを評価に反映します。' }
    ]},
    { id: 'auction', icon: '🚗', title: '出品（オークション）マニュアル', desc: '出品票作成から落札まで', lessons: [
      { t: '出品の流れ', video: '', text: '申込→査定→出品票作成→会場搬入→セリ→落札→名義変更。各会場のルールに従います。' },
      { t: '出品票の作り方', video: '', text: '車両情報・評価点・修復歴・装備を正確に記載。減点項目は正直に記載することでクレームを防ぎます。' },
      { t: '陸送・名義変更の手配', video: '', text: 'ドアtoドアの陸送概算、名義変更・抹消登録の必要書類と段取り。' }
    ]},
    { id: 'sales', icon: '🤝', title: '接客・クロージング', desc: '成約率を高める対話', lessons: [
      { t: '電話・来店の初期対応', video: '', text: '安心感を与える挨拶と要件確認。出張査定の日程調整までをスムーズに。' },
      { t: '提示と反論処理', video: '', text: '金額提示の根拠を伝える。「他社が高い」への対応はトークスクリプト集を参照。' },
      { t: 'クロージング', video: '', text: '契約の流れと必要書類の案内、入金までの安心設計。' }
    ]},
    { id: 'system', icon: '💻', title: 'システム操作（看板/リード）', desc: '日々の案件管理', lessons: [
      { t: 'リードの確認と担当割当', video: '', text: 'リード一覧で新規を確認し、自店に割り当て。検索・フィルタの使い方。' },
      { t: '看板ボードでの進捗管理', video: '', text: 'カードをドラッグしてステージを移動。詳細パネルで対応履歴を記録します。' },
      { t: '会員マイページとの連動', video: '', text: 'ステージ更新はお客様のマイページにも反映。こまめな更新が信頼に繋がります。' }
    ]}
  ];

  function getProgress() { try { return JSON.parse(localStorage.getItem(PKEY)) || {}; } catch (e) { return {}; } }
  function setProgress(p) { try { localStorage.setItem(PKEY, JSON.stringify(p)); } catch (e) {} }
  function done(courseId) { var p = getProgress(); return (p[courseId] || []); }
  function isDone(courseId, idx) { return done(courseId).indexOf(idx) >= 0; }
  function complete(courseId, idx, on) {
    var p = getProgress(); var a = p[courseId] || [];
    var i = a.indexOf(idx);
    if (on && i < 0) a.push(idx);
    if (!on && i >= 0) a.splice(i, 1);
    p[courseId] = a; setProgress(p);
  }
  function pct(courseId) { var c = byId(courseId); if (!c) return 0; return Math.round(done(courseId).length / c.lessons.length * 100); }
  function byId(id) { for (var i = 0; i < COURSES.length; i++) if (COURSES[i].id === id) return COURSES[i]; return null; }
  function overall() {
    var total = 0, dn = 0;
    COURSES.forEach(function (c) { total += c.lessons.length; dn += done(c.id).length; });
    return total ? Math.round(dn / total * 100) : 0;
  }

  function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

  /* ハブ描画 */
  function renderHub() {
    var grid = document.getElementById('academyGrid'); if (!grid) return;
    var ov = document.getElementById('overallPct'); if (ov) ov.textContent = overall() + '%';
    var ob = document.getElementById('overallBar'); if (ob) ob.style.width = overall() + '%';
    grid.innerHTML = COURSES.map(function (c) {
      var p = pct(c.id);
      return '<a class="ac-card" href="partner-course.html?id=' + c.id + '">' +
        '<div class="ac-ico">' + c.icon + '</div>' +
        '<h3>' + esc(c.title) + '</h3><p>' + esc(c.desc) + '</p>' +
        '<div class="ac-bar"><span style="width:' + p + '%"></span></div>' +
        '<div class="ac-meta">' + done(c.id).length + '/' + c.lessons.length + ' レッスン完了（' + p + '%）</div>' +
      '</a>';
    }).join('');
  }

  /* 受講画面描画 */
  function renderCourse() {
    var root = document.getElementById('courseRoot'); if (!root) return;
    var id; try { id = new URLSearchParams(location.search).get('id'); } catch (e) { id = null; }
    var c = byId(id) || COURSES[0];
    var cur = 0;

    function lessonHTML(l) {
      var media = l.video
        ? '<div class="cv-video"><iframe src="' + esc(l.video) + '" allowfullscreen loading="lazy"></iframe></div>'
        : '<div class="cv-video placeholder">▶ 動画（差し替え用プレースホルダー）</div>';
      return media + '<div class="cv-text">' + esc(l.text) + '</div>';
    }
    function draw() {
      document.getElementById('cvTitle').textContent = c.title;
      document.getElementById('cvList').innerHTML = c.lessons.map(function (l, i) {
        return '<li class="' + (i === cur ? 'active ' : '') + (isDone(c.id, i) ? 'done' : '') + '" data-i="' + i + '">' +
          '<span class="cv-check">' + (isDone(c.id, i) ? '✓' : (i + 1)) + '</span>' + esc(l.t) + '</li>';
      }).join('');
      document.getElementById('cvLessonTitle').textContent = c.lessons[cur].t;
      document.getElementById('cvBody').innerHTML = lessonHTML(c.lessons[cur]);
      var btn = document.getElementById('cvComplete');
      btn.textContent = isDone(c.id, cur) ? '完了済み（取り消す）' : 'このレッスンを完了にする';
      btn.classList.toggle('is-done', isDone(c.id, cur));
      document.getElementById('cvProg').textContent = done(c.id).length + '/' + c.lessons.length + '（' + pct(c.id) + '%）';
    }
    document.getElementById('cvList').addEventListener('click', function (e) {
      var li = e.target.closest('li'); if (!li) return; cur = Number(li.getAttribute('data-i')); draw();
    });
    document.getElementById('cvComplete').addEventListener('click', function () {
      complete(c.id, cur, !isDone(c.id, cur));
      if (cur < c.lessons.length - 1 && isDone(c.id, cur)) cur++;
      draw();
    });
    draw();
  }

  return { COURSES: COURSES, renderHub: renderHub, renderCourse: renderCourse, overall: overall };
})();
