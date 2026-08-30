# Certify LMS

マルチ資格対応の資格学習プラットフォームです。受講生は資格ごとの教材で学習し、演習問題・模擬試験で理解度を確かめながら、コーチの面談サポートを受けて資格取得を目指せます。

> プロジェクト構造・ドメインモデル・コードの読み進め方は [ONBOARDING.md](./ONBOARDING.md) を参照してください。

## 主な機能

| ロール | 機能 |
|---|---|
| 受講生（student） | 教材閲覧 / 演習問題・苦手分野ドリル / 模擬試験（分野別ヒートマップ・合格可能性スコア）/ 面談予約 / チャット / 学習時間・進捗・ストリーク管理 / 修了証の受領 |
| コーチ（coach） | 教材・演習問題・模試の管理 / 担当受講生の進捗フォロー / 面談対応・面談メモ / チャット |
| 管理者（admin） | ユーザー招待・管理 / 資格・資格分類マスタ管理 / 資格へのコーチ割当 / 面談回数の付与 / 全体ダッシュボード |

## 動作環境

- Docker Desktop / Docker Compose
- 開発環境は Laravel Sail で構築します（PHP コンテナ・MySQL・Mailpit・phpMyAdmin を起動）

## 環境構築手順

### 1. リポジトリの clone

```bash
git clone <このリポジトリの URL>
cd <リポジトリ名>
```

### 2. 環境変数ファイルの作成

```bash
cp .env.example .env
```

`.env.example` は Sail 向けに設定済みのため、コピーするだけでローカル開発を始められます（外部サービス連携のキーは後述）。

### 3. 依存パッケージのインストール（初回のみ）

`vendor/` がまだ無いため、初回のみ Docker 経由で Composer を実行します。

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php84-composer:latest \
    composer install --ignore-platform-reqs
```

### 4. Sail エイリアスの設定（推奨）

```bash
alias sail='./vendor/bin/sail'
```

以降のコマンドはこのエイリアス前提で記載します（未設定の場合は `./vendor/bin/sail` に読み替えてください）。

### 5. コンテナの起動

```bash
sail up -d
```

### 6. アプリケーションの初期化

```bash
sail artisan key:generate
sail artisan storage:link
sail artisan migrate:fresh --seed
```

`storage:link` は教材画像・プロフィール画像の配信に必要です。`migrate:fresh --seed` でテーブル作成とデモデータ投入が行われます（いつでも再実行してデータを初期状態に戻せます）。

### 7. フロントエンドのビルド

```bash
sail npm install
sail npm run build
```

Blade / CSS / JS を編集しながら開発する場合は、`build` の代わりに `sail npm run dev` を起動したままにしてください（Vite のホットリロードが効きます）。

### 8. 動作確認

http://localhost:8000 にアクセスし、下記の[ログインアカウント](#ログインアカウント)でログインできればセットアップ完了です。

## 開発環境 URL

| 用途 | URL |
|---|---|
| アプリケーション | http://localhost:8000 |
| phpMyAdmin（DB 確認） | http://localhost:8080 |
| Mailpit（メール確認） | http://localhost:8025 |

アプリケーションが送信するメール（招待メールなど）はすべて Mailpit に届きます。実際のメールは送信されません。

## ログインアカウント

`migrate:fresh --seed` 後、以下の固定アカウントが使えます（パスワードはすべて `password`）。

| ロール | メールアドレス | 備考 |
|---|---|---|
| 管理者 | admin@certify-lms.test | 全機能にアクセス可能 |
| コーチ | coach@certify-lms.test | IT 系資格の担当 |
| コーチ | coach2@certify-lms.test | ビジネス系資格の担当 |
| 受講生 | student@certify-lms.test | 受講中の資格・学習履歴・面談などのデモデータ付き |

このほか、ライフサイクル（招待中 / 受講中 / 卒業 / 退会）を網羅したデモユーザーが投入されます。

> 本サービスは**招待制**です。公開の会員登録画面はありません。新規ユーザーを作るには、管理者でログイン → ユーザー管理から招待 → Mailpit で招待メールの URL を開く → オンボーディング登録、という流れになります。

## テスト

```bash
sail artisan test                  # 全テスト実行
sail artisan test --filter=Xxx    # クラス名・メソッド名で絞り込み
```

## コード整形

Laravel Pint を使用しています。コミット前に実行してください。

```bash
sail bin pint --dirty    # 変更ファイルのみ整形
sail bin pint --test     # 整形漏れの確認（CI 相当のチェック）
```

## 使用技術

- PHP 8.5 / Laravel 10
- MySQL 8.4
- Laravel Fortify（認証）/ Laravel Sanctum（API 認証）
- Blade + Tailwind CSS + Vite（JavaScript は素の JS、フレームワーク不使用）
- PHPUnit / Laravel Pint
- league/commonmark（教材本文の Markdown レンダリング）
- mpdf/mpdf（修了証 PDF 生成。日本語表記のため IPA ゴシック `resources/fonts/ipag.ttf`（IPA Font License v1.0、同梱)を使用)
- stripe/stripe-php（追加面談パック購入の決済連携）
- Pusher（チャットのリアルタイム配信）
- Docker（Laravel Sail）

## 環境変数

`.env.example` をコピーするだけで、すべての機能がローカルで動作します（メールは Mailpit に配信されます）。

- `PUSHER_*` — チャットのリアルタイム配信に使用します。有効にする場合は Pusher のキーを取得して設定し、`BROADCAST_DRIVER=pusher` に変更してください。未設定（既定の `BROADCAST_DRIVER=log`）でもメッセージの送受信自体は動作し、相手画面へのリアルタイム反映のみ行われません

- `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` — コーチの Google カレンダー連携(面談予約)に使用します。[Google Cloud Console](https://console.cloud.google.com/) で OAuth クライアント(ウェブアプリケーション)を作成し、`GOOGLE_REDIRECT_URI` と同じ値を「承認済みのリダイレクト URI」に登録してください。未設定でも面談機能自体は動作し続けます(連携カードは表示されますが、実際の接続のみ失敗します)。

  > **本番運用時の注意(セキュリティ)**: `google_credentials` テーブルの `access_token` / `refresh_token` は現状 **平文で保存**されます(本チケットのスコープ外)。本番運用では、Laravel の暗号化キャスト(`'access_token' => 'encrypted'` 等を `GoogleCredential::$casts` に追加)や、DB 列レベルの暗号化(カラム暗号化 / KMS 連携)の導入を別途検討してください。

- `AI_CHAT_ENABLED` — AI 相談(Gemini チャットボット)機能のスイッチです。`false`(既定)の間は `/ai-chat` のルート自体が登録されず、フローティングウィジェット・フル画面とも一切表示・利用できません。

- `GEMINI_API_KEY` / `GEMINI_MODEL` — AI 相談が Gemini API(Generative Language API)に問い合わせる際に使用します。[Google AI Studio](https://aistudio.google.com/apikey) で API キーを発行し、`GEMINI_API_KEY` に設定してください(Google アカウントでログイン → 「Get API key」→「Create API key」)。未設定でも `AI_CHAT_ENABLED=true` であれば画面・ルートは動作しますが、AI 応答は常にエラー(「AI が応答できませんでした」)としてグレースフルに失敗します。無料枠の範囲でも動作確認は可能です。

- `STRIPE_SECRET_KEY` / `STRIPE_WEBHOOK_SECRET` — 追加面談パックの購入(Stripe Checkout)に使用します。

  1. [Stripe ダッシュボード](https://dashboard.stripe.com/register)にアカウント登録(テストモードのみでよく、本人確認や銀行口座登録は不要です)。
  2. ダッシュボード右上が「テスト環境」になっていることを確認し、「開発者」→「API キー」から **シークレットキー**(`sk_test_...`)をコピーして `STRIPE_SECRET_KEY` に設定します。
  3. ローカルで Webhook 通知を受け取るには [Stripe CLI](https://docs.stripe.com/stripe-cli) が必要です。`brew install stripe/stripe-cli/stripe`(または[配布ページ](https://github.com/stripe/stripe-cli/releases/latest)からダウンロード)でインストール後、`stripe login` でアカウント連携します。
  4. 以下のコマンドを起動したままにすると、Stripe が送る Webhook イベントがローカルの `/webhooks/stripe` へ転送されます。

     ```bash
     stripe listen --forward-to localhost:8000/webhooks/stripe
     ```

     起動時に表示される `Your webhook signing secret is whsec_...` の値を `STRIPE_WEBHOOK_SECRET` に設定してください(`stripe listen` を起動し直すたびに値が変わるため、都度更新が必要です)。
  5. `sail artisan config:clear` の後、`/meeting-quota/checkout` から購入を試すと Stripe のホストする決済ページへ遷移します。テスト用カード番号 `4242 4242 4242 4242`(有効期限は未来の任意の月、CVC は任意の 3 桁)で決済を完了すると、`stripe listen` のログにイベント転送が表示され、数秒後に残面談回数へ反映されます。
  6. `STRIPE_SECRET_KEY` が未設定でも `/meeting-quota/checkout` 自体は表示されますが、購入ボタン押下時にエラーメッセージを表示してグレースフルに失敗します(実際の決済処理は行われません)。

新しい環境変数やセットアップ手順を追加した場合は、`.env.example` と本 README に追記し、チームの誰でも環境を再現できる状態を保ってください。
