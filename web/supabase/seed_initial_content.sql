-- ============================================================
-- IWASAWA SURF BASE — 初期コンテンツ（講座・レッスン・ブログ）
-- Supabase 接続後、最初の管理者（admin）アカウントを作ってから SQL Editor で1回だけ実行。
-- 何度実行しても重複しない（同じタイトルがあればスキップ）。
-- 講師はご本人の会員登録が必要なため、ここでは作成しません。
-- ============================================================
begin;

-- 講座：海デビュー 初心者コース
insert into public.courses (title, description, level, cover_url, status, sort_order)
select '海デビュー 初心者コース', 'まったくの初めてから、自分で海に入れるようになるまで。ルール・パドリング・テイクオフを、動画とマニュアルで。', 'beginner', '/courses/beginner.png', 'active', 0
where not exists (select 1 from public.courses where title = '海デビュー 初心者コース');
with c as (select id from public.courses where title = '海デビュー 初心者コース' limit 1),
l as (
  insert into public.lessons (course_id, title, is_free, duration_min, sort_order)
  select c.id, '第1回：海のルールと安全（無料）', true, 8, 0 from c
  where not exists (select 1 from public.lessons x where x.course_id = c.id and x.title = '第1回：海のルールと安全（無料）')
  returning id
)
insert into public.lesson_contents (lesson_id, body) select id, 'サーフィンは自然が相手のスポーツです。まず身につけたいのは『安全』と『ルール』。

■ 一本の波に一人
ピーク（波の割れ始め）に近い人が優先。すでに乗っている人の前に入る『ドロップイン（前乗り）』は絶対に避けます。

■ 離岸流を知る
沖へ流れる速い流れ。捕まったら岸に平行に泳いで抜けます。逆らって岸へ戻ろうとしないこと。

■ 自分のレベルで
サイズ・風・混雑を確認し、無理をしない。初めは膝〜腰の深さ、空いた場所で練習しましょう。' from l;
with c as (select id from public.courses where title = '海デビュー 初心者コース' limit 1),
l as (
  insert into public.lessons (course_id, title, is_free, duration_min, sort_order)
  select c.id, '第2回：パドリングの基礎', false, 10, 1 from c
  where not exists (select 1 from public.lessons x where x.course_id = c.id and x.title = '第2回：パドリングの基礎')
  returning id
)
insert into public.lesson_contents (lesson_id, body) select id, 'パドリングはサーフィンの土台。ここが安定すると、上達が一気に早くなります。

■ ボードの上の位置
ノーズ（先端）が水面から握りこぶし1つ分出るくらい。前すぎると刺さり、後ろすぎると進みません。

■ 手のかき方
体の中心線に沿って、水を後ろへ長く押す。左右交互に、あわてず大きく。

■ 目線
進みたい方向を見る。下を向くと失速します。' from l;
with c as (select id from public.courses where title = '海デビュー 初心者コース' limit 1),
l as (
  insert into public.lessons (course_id, title, is_free, duration_min, sort_order)
  select c.id, '第3回：テイクオフ', false, 12, 2 from c
  where not exists (select 1 from public.lessons x where x.course_id = c.id and x.title = '第3回：テイクオフ')
  returning id
)
insert into public.lesson_contents (lesson_id, body) select id, '波に乗って立ち上がる一連の動き。陸トレで体に覚えさせてから海へ。

■ タイミング
波が近づいたら強くパドル。ボードが波に押される感覚を掴む。

■ 立ち上がり
胸を起こし、後ろ足→前足の順にすばやく。目線は前。' from l;
with c as (select id from public.courses where title = '海デビュー 初心者コース' limit 1),
l as (
  insert into public.lessons (course_id, title, is_free, duration_min, sort_order)
  select c.id, '第4回：ゲッティングアウト（沖に出る）', false, 9, 3 from c
  where not exists (select 1 from public.lessons x where x.course_id = c.id and x.title = '第4回：ゲッティングアウト（沖に出る）')
  returning id
)
insert into public.lesson_contents (lesson_id, body) select id, '沖に出るときは、乗っている人の進路を避けて回り込みます。白波の下をくぐる、押し返される力の逃し方など、安全に出るコツを解説します。' from l;

-- 講座：来訪前の準備・事前学習
insert into public.courses (title, description, level, cover_url, status, sort_order)
select '来訪前の準備・事前学習', '県外から岩沢へ来る前に。持ち物・装備・海の入り方を、来る前に家で予習。', 'prep', '/courses/prep.png', 'active', 1
where not exists (select 1 from public.courses where title = '来訪前の準備・事前学習');
with c as (select id from public.courses where title = '来訪前の準備・事前学習' limit 1),
l as (
  insert into public.lessons (course_id, title, is_free, duration_min, sort_order)
  select c.id, '第1回：持ち物と装備（無料）', true, 6, 0 from c
  where not exists (select 1 from public.lessons x where x.course_id = c.id and x.title = '第1回：持ち物と装備（無料）')
  returning id
)
insert into public.lesson_contents (lesson_id, body) select id, '初回に必要なのは、ボード・ウェット・リーシュの3つ。あとはタオル・水・日焼け対策。

レンタルも使えるので手ぶらでもOK。水温に合わせたウェット選びは、スクールやショップに相談を。' from l;
with c as (select id from public.courses where title = '来訪前の準備・事前学習' limit 1),
l as (
  insert into public.lessons (course_id, title, is_free, duration_min, sort_order)
  select c.id, '第2回：岩沢海岸の入り方・注意点', false, 8, 1 from c
  where not exists (select 1 from public.lessons x where x.course_id = c.id and x.title = '第2回：岩沢海岸の入り方・注意点')
  returning id
)
insert into public.lesson_contents (lesson_id, body) select id, '駐車場所、エントリーとカレント、混雑する時間帯、地元のマナー。初めての岩沢を安心して楽しむための実践情報。' from l;

-- 講座：ステップアップコース
insert into public.courses (title, description, level, cover_url, status, sort_order)
select 'ステップアップコース', 'テイクオフができた次へ。横に走る・ターンの基礎を身につける。', 'intermediate', '/courses/stepup.png', 'active', 2
where not exists (select 1 from public.courses where title = 'ステップアップコース');
with c as (select id from public.courses where title = 'ステップアップコース' limit 1),
l as (
  insert into public.lessons (course_id, title, is_free, duration_min, sort_order)
  select c.id, '第1回：横に走る（トリム）', false, 11, 0 from c
  where not exists (select 1 from public.lessons x where x.course_id = c.id and x.title = '第1回：横に走る（トリム）')
  returning id
)
insert into public.lesson_contents (lesson_id, body) select id, '波の斜面（フェイス）を横に走るための重心と目線。板を走らせる感覚を掴みます。' from l;
with c as (select id from public.courses where title = 'ステップアップコース' limit 1),
l as (
  insert into public.lessons (course_id, title, is_free, duration_min, sort_order)
  select c.id, '第2回：ボトムターン', false, 12, 1 from c
  where not exists (select 1 from public.lessons x where x.course_id = c.id and x.title = '第2回：ボトムターン')
  returning id
)
insert into public.lesson_contents (lesson_id, body) select id, 'すべての基本となるターン。踏み込みのタイミングと体の使い方を解説します。' from l;

-- ブログ（最初の管理者の名前で掲載。管理者がいなければスキップ）
insert into public.articles (author_id, title, body, status, published_at)
select m.id, '初心者がまず揃えるべき3つの道具', 'サーフィンを始めるとき、道具選びで迷う人はとても多いです。結論から言うと、最初に必要なのは『ボード・ウェット・リーシュコード』の3つ。

ボードは浮力のあるファンボード〜ロングがおすすめ。岩沢のようなメローな波なら、無理に短い板を選ばず、まずは安定して立てる一本を。ウェットは水温に合わせて、迷ったらショップかスクールで相談を。

道具は『続けられる』ことが一番大事。最初から高価なものを揃える必要はありません。', 'published', '2026-06-14'
from public.members m
where m.role = 'admin' and not exists (select 1 from public.articles where title = '初心者がまず揃えるべき3つの道具')
order by m.created_at limit 1;
insert into public.articles (author_id, title, body, status, published_at)
select m.id, '海が怖い人へ。安全に楽しむ5つの心得', '海が怖いと感じるのは、とても自然なことです。むしろその感覚は大切にしてください。

①自分の膝〜腰くらいの深さから始める ②離岸流（リップカレント）を知る ③体調が悪い日は入らない ④一人では入らない ⑤無理をしない。

この5つを守れば、海はぐっと身近になります。焦らず、自分のペースで。', 'published', '2026-06-12'
from public.members m
where m.role = 'admin' and not exists (select 1 from public.articles where title = '海が怖い人へ。安全に楽しむ5つの心得')
order by m.created_at limit 1;
insert into public.articles (author_id, title, body, status, published_at)
select m.id, '岩沢海岸のシーズン別コンディション', '岩沢海岸は年間を通して楽しめるビーチブレイク。春〜初夏はメローで初心者に最適、秋は台風うねりでサイズアップ。

朝イチは風が弱く、面がきれいなことが多いのでおすすめです。地元の波情報とローカルの声もチェックして、その日のベストな時間に入りましょう。', 'published', '2026-06-10'
from public.members m
where m.role = 'admin' and not exists (select 1 from public.articles where title = '岩沢海岸のシーズン別コンディション')
order by m.created_at limit 1;

commit;
