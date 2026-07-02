/**
 * AUC-AGENT Firebase 設定ファイル
 * ================================
 *
 * 【セットアップ手順】
 * 1. Firebase コンソール (https://console.firebase.google.com/) でプロジェクトを作成
 * 2. 「Authentication」を有効化し、メール/パスワードとGoogleプロバイダを設定
 * 3. 「Cloud Firestore」を有効化（本番モードまたはテストモード）
 * 4. 下記の firebaseConfig オブジェクトのプレースホルダーを実際の値に置き換え
 * 5. このファイルを app.js より前に <script> タグで読み込む
 *
 * 【CDN スクリプトの読み込み順序（HTMLに追加）】
 *   <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
 *   <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-auth-compat.js"></script>
 *   <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-firestore-compat.js"></script>
 *   <script src="assets/js/firebase-config.js"></script>
 *   <script src="assets/js/app.js"></script>
 *
 * 【Firestore データ構造】
 *   members/{uid}                  - 会員情報（name, email, plan, since, phone）
 *   members/{uid}/orders/{orderId} - 注文データ
 *   members/{uid}/quotes/{quoteId} - 見積もりデータ
 *
 * 【注意】
 *   Firebase が未設定の場合、サイトは自動的にデモモード（localStorage）で動作します。
 */

(function () {
  'use strict';

  // ============================================================
  // Firebase 設定 — 下記のプレースホルダーを実際の値に置き換えてください
  // ============================================================
  var firebaseConfig = {
    apiKey: 'REPLACE_WITH_YOUR_API_KEY',
    authDomain: 'REPLACE_WITH_YOUR_AUTH_DOMAIN',
    projectId: 'REPLACE_WITH_YOUR_PROJECT_ID',
    storageBucket: 'REPLACE_WITH_YOUR_STORAGE_BUCKET',
    messagingSenderId: 'REPLACE_WITH_YOUR_MESSAGING_SENDER_ID',
    appId: 'REPLACE_WITH_YOUR_APP_ID'
  };

  // ============================================================
  // 内部状態
  // ============================================================
  var _enabled = false;
  var _app = null;
  var _auth = null;
  var _db = null;

  /**
   * 設定値がプレースホルダーのままかどうかを判定
   */
  function isPlaceholder(value) {
    return !value || typeof value !== 'string' || value.indexOf('REPLACE_WITH_YOUR_') === 0;
  }

  /**
   * 設定が有効かチェック
   */
  function isConfigValid() {
    return !isPlaceholder(firebaseConfig.apiKey) &&
           !isPlaceholder(firebaseConfig.authDomain) &&
           !isPlaceholder(firebaseConfig.projectId);
  }

  /**
   * 統一レスポンス形式を生成
   */
  function ok(data) {
    return { ok: true, data: data || null };
  }

  function fail(error) {
    var message = (error && error.message) ? error.message : String(error);
    return { ok: false, error: message };
  }

  /**
   * 現在のユーザーUIDを取得（未ログイン時は null）
   */
  function getUid() {
    var user = _auth && _auth.currentUser;
    return user ? user.uid : null;
  }

  // ============================================================
  // 公開 API
  // ============================================================
  window.AucFirebase = {

    /**
     * Firebase を初期化する
     * 設定が未入力の場合は false を返し、サイトはデモモードで動作する
     */
    init: function () {
      if (_enabled) return true;

      if (!isConfigValid()) {
        console.info('[AucFirebase] Firebase 設定が未入力のため、デモモード（localStorage）で動作します。');
        return false;
      }

      try {
        // firebase compat SDK がロードされているか確認
        if (typeof firebase === 'undefined') {
          console.warn('[AucFirebase] Firebase SDK が読み込まれていません。CDN スクリプトを追加してください。');
          return false;
        }

        _app = firebase.initializeApp(firebaseConfig);
        _auth = firebase.auth();
        _db = firebase.firestore();
        _enabled = true;
        console.info('[AucFirebase] Firebase 初期化完了');
        return true;
      } catch (e) {
        console.error('[AucFirebase] Firebase 初期化エラー:', e);
        return false;
      }
    },

    /**
     * Firebase が有効かどうか
     */
    isEnabled: function () {
      return _enabled;
    },

    /**
     * 新規会員登録
     * @param {string} name - 氏名
     * @param {string} email - メールアドレス
     * @param {string} password - パスワード
     * @param {string} plan - 会員プラン
     * @returns {Promise<{ok: boolean, data?: any, error?: string}>}
     */
    register: function (name, email, password, plan) {
      if (!_enabled) return Promise.resolve(fail('Firebase は無効です'));

      return _auth.createUserWithEmailAndPassword(email, password)
        .then(function (credential) {
          var uid = credential.user.uid;
          var profile = {
            name: name,
            email: email,
            plan: plan || 'free',
            since: new Date().toISOString(),
            phone: ''
          };
          return _db.collection('members').doc(uid).set(profile)
            .then(function () {
              return ok({ uid: uid, profile: profile });
            });
        })
        .catch(function (e) {
          return fail(e);
        });
    },

    /**
     * メール/パスワードでログイン
     * @param {string} email
     * @param {string} password
     * @returns {Promise<{ok: boolean, data?: any, error?: string}>}
     */
    login: function (email, password) {
      if (!_enabled) return Promise.resolve(fail('Firebase は無効です'));

      return _auth.signInWithEmailAndPassword(email, password)
        .then(function (credential) {
          return ok({ uid: credential.user.uid, email: credential.user.email });
        })
        .catch(function (e) {
          return fail(e);
        });
    },

    /**
     * Google アカウントでログイン
     * @returns {Promise<{ok: boolean, data?: any, error?: string}>}
     */
    loginWithGoogle: function () {
      if (!_enabled) return Promise.resolve(fail('Firebase は無効です'));

      var provider = new firebase.auth.GoogleAuthProvider();
      return _auth.signInWithPopup(provider)
        .then(function (result) {
          var user = result.user;
          var uid = user.uid;
          // Firestore にプロフィールがなければ作成
          return _db.collection('members').doc(uid).get()
            .then(function (doc) {
              if (!doc.exists) {
                var profile = {
                  name: user.displayName || '',
                  email: user.email || '',
                  plan: 'free',
                  since: new Date().toISOString(),
                  phone: ''
                };
                return _db.collection('members').doc(uid).set(profile)
                  .then(function () {
                    return ok({ uid: uid, email: user.email, isNewUser: true });
                  });
              }
              return ok({ uid: uid, email: user.email, isNewUser: false });
            });
        })
        .catch(function (e) {
          return fail(e);
        });
    },

    /**
     * ログアウト
     * @returns {Promise<{ok: boolean, error?: string}>}
     */
    logout: function () {
      if (!_enabled) return Promise.resolve(fail('Firebase は無効です'));

      return _auth.signOut()
        .then(function () {
          return ok();
        })
        .catch(function (e) {
          return fail(e);
        });
    },

    /**
     * 認証状態の変化を監視
     * @param {function} callback - ユーザーオブジェクトまたは null を受け取る
     * @returns {function|null} リスナー解除関数
     */
    onAuthChange: function (callback) {
      if (!_enabled) return null;

      return _auth.onAuthStateChanged(function (user) {
        callback(user || null);
      });
    },

    /**
     * 現在のログインユーザーを取得
     * @returns {object|null}
     */
    currentUser: function () {
      if (!_enabled) return null;
      return _auth ? _auth.currentUser : null;
    },

    /**
     * 現在のユーザーの会員プロフィールを取得
     * @returns {Promise<{ok: boolean, data?: any, error?: string}>}
     */
    getProfile: function () {
      if (!_enabled) return Promise.resolve(fail('Firebase は無効です'));

      var uid = getUid();
      if (!uid) return Promise.resolve(fail('ログインしていません'));

      return _db.collection('members').doc(uid).get()
        .then(function (doc) {
          if (!doc.exists) return fail('プロフィールが見つかりません');
          return ok(doc.data());
        })
        .catch(function (e) {
          return fail(e);
        });
    },

    /**
     * 現在のユーザーの会員プロフィールを更新
     * @param {object} data - 更新するフィールド
     * @returns {Promise<{ok: boolean, error?: string}>}
     */
    updateProfile: function (data) {
      if (!_enabled) return Promise.resolve(fail('Firebase は無効です'));

      var uid = getUid();
      if (!uid) return Promise.resolve(fail('ログインしていません'));

      return _db.collection('members').doc(uid).update(data)
        .then(function () {
          return ok();
        })
        .catch(function (e) {
          return fail(e);
        });
    },

    /**
     * 注文を追加
     * @param {object} orderData - 注文データ
     * @returns {Promise<{ok: boolean, data?: any, error?: string}>}
     */
    addOrder: function (orderData) {
      if (!_enabled) return Promise.resolve(fail('Firebase は無効です'));

      var uid = getUid();
      if (!uid) return Promise.resolve(fail('ログインしていません'));

      var record = Object.assign({}, orderData, {
        createdAt: new Date().toISOString()
      });

      return _db.collection('members').doc(uid)
        .collection('orders').add(record)
        .then(function (docRef) {
          return ok({ id: docRef.id });
        })
        .catch(function (e) {
          return fail(e);
        });
    },

    /**
     * 現在のユーザーの全注文を取得
     * @returns {Promise<{ok: boolean, data?: any, error?: string}>}
     */
    getOrders: function () {
      if (!_enabled) return Promise.resolve(fail('Firebase は無効です'));

      var uid = getUid();
      if (!uid) return Promise.resolve(fail('ログインしていません'));

      return _db.collection('members').doc(uid)
        .collection('orders').orderBy('createdAt', 'desc').get()
        .then(function (snapshot) {
          var orders = [];
          snapshot.forEach(function (doc) {
            orders.push(Object.assign({ id: doc.id }, doc.data()));
          });
          return ok(orders);
        })
        .catch(function (e) {
          return fail(e);
        });
    },

    /**
     * 見積もりリクエストを追加
     * @param {object} quoteData - 見積もりデータ
     * @returns {Promise<{ok: boolean, data?: any, error?: string}>}
     */
    addQuote: function (quoteData) {
      if (!_enabled) return Promise.resolve(fail('Firebase は無効です'));

      var uid = getUid();
      if (!uid) return Promise.resolve(fail('ログインしていません'));

      var record = Object.assign({}, quoteData, {
        createdAt: new Date().toISOString()
      });

      return _db.collection('members').doc(uid)
        .collection('quotes').add(record)
        .then(function (docRef) {
          return ok({ id: docRef.id });
        })
        .catch(function (e) {
          return fail(e);
        });
    },

    /**
     * 現在のユーザーの全見積もりを取得
     * @returns {Promise<{ok: boolean, data?: any, error?: string}>}
     */
    getQuotes: function () {
      if (!_enabled) return Promise.resolve(fail('Firebase は無効です'));

      var uid = getUid();
      if (!uid) return Promise.resolve(fail('ログインしていません'));

      return _db.collection('members').doc(uid)
        .collection('quotes').orderBy('createdAt', 'desc').get()
        .then(function (snapshot) {
          var quotes = [];
          snapshot.forEach(function (doc) {
            quotes.push(Object.assign({ id: doc.id }, doc.data()));
          });
          return ok(quotes);
        })
        .catch(function (e) {
          return fail(e);
        });
    }
  };
})();
