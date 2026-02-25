<?php
/**
 * APIコスト計算クラス
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAIC_Cost_Calculator {

    /**
     * モデル別の料金（1Mトークンあたりのドル）
     * 2024年12月時点の料金
     */
    private static function get_pricing(): array {
        return [
            // OpenAI Models
            'gpt-4o' => [
                'input'  => 2.50,
                'output' => 10.00,
            ],
            'gpt-4o-2024-11-20' => [
                'input'  => 2.50,
                'output' => 10.00,
            ],
            'gpt-4o-2024-08-06' => [
                'input'  => 2.50,
                'output' => 10.00,
            ],
            'gpt-4o-mini' => [
                'input'  => 0.15,
                'output' => 0.60,
            ],
            'gpt-4o-mini-2024-07-18' => [
                'input'  => 0.15,
                'output' => 0.60,
            ],
            'gpt-4-turbo' => [
                'input'  => 10.00,
                'output' => 30.00,
            ],
            'gpt-4-turbo-preview' => [
                'input'  => 10.00,
                'output' => 30.00,
            ],
            'gpt-4' => [
                'input'  => 30.00,
                'output' => 60.00,
            ],
            'gpt-3.5-turbo' => [
                'input'  => 0.50,
                'output' => 1.50,
            ],
            'o1' => [
                'input'  => 15.00,
                'output' => 60.00,
            ],
            'o1-preview' => [
                'input'  => 15.00,
                'output' => 60.00,
            ],
            'o1-mini' => [
                'input'  => 3.00,
                'output' => 12.00,
            ],
            'o3-mini' => [
                'input'  => 1.10,
                'output' => 4.40,
            ],

            // Claude Models
            'claude-opus-4-5-20251101' => [
                'input'  => 15.00,
                'output' => 75.00,
            ],
            'claude-sonnet-4-20250514' => [
                'input'  => 3.00,
                'output' => 15.00,
            ],
            'claude-3-5-sonnet-20241022' => [
                'input'  => 3.00,
                'output' => 15.00,
            ],
            'claude-3-5-haiku-20241022' => [
                'input'  => 0.80,
                'output' => 4.00,
            ],
            'claude-3-opus-20240229' => [
                'input'  => 15.00,
                'output' => 75.00,
            ],
            'claude-3-sonnet-20240229' => [
                'input'  => 3.00,
                'output' => 15.00,
            ],
            'claude-3-haiku-20240307' => [
                'input'  => 0.25,
                'output' => 1.25,
            ],

            // Gemini Models (Free tier available, but paid tier pricing)
            'gemini-2.0-flash-exp' => [
                'input'  => 0.00,  // Free during experimental
                'output' => 0.00,
            ],
            'gemini-2.0-flash' => [
                'input'  => 0.10,
                'output' => 0.40,
            ],
            'gemini-1.5-pro' => [
                'input'  => 1.25,
                'output' => 5.00,
            ],
            'gemini-1.5-flash' => [
                'input'  => 0.075,
                'output' => 0.30,
            ],
            'gemini-1.5-flash-8b' => [
                'input'  => 0.0375,
                'output' => 0.15,
            ],
            'gemini-1.0-pro' => [
                'input'  => 0.50,
                'output' => 1.50,
            ],
        ];
    }

    /**
     * 特定モデルのコストを計算
     */
    public static function calculate_cost(string $model, int $input_tokens, int $output_tokens): float {
        $pricing = self::get_pricing();

        // モデル名の正規化（バージョン番号なしでも検索）
        $model_key = self::find_model_key($model, $pricing);

        if (!$model_key) {
            // 不明なモデルのプロバイダー別フォールバック
            if (strpos($model, 'claude') === 0) {
                // Claude 3.5 Sonnet相当
                $input_rate = 3.00;
                $output_rate = 15.00;
            } elseif (strpos($model, 'gemini') === 0) {
                // Gemini 1.5 Flash相当
                $input_rate = 0.075;
                $output_rate = 0.30;
            } else {
                // GPT-4o-mini相当
                $input_rate = 0.15;
                $output_rate = 0.60;
            }
        } else {
            $input_rate = $pricing[$model_key]['input'];
            $output_rate = $pricing[$model_key]['output'];
        }

        // 1Mトークンあたりの料金をトークン単価に変換して計算
        $input_cost = ($input_tokens / 1000000) * $input_rate;
        $output_cost = ($output_tokens / 1000000) * $output_rate;

        return $input_cost + $output_cost;
    }

    /**
     * モデルキーを検索（部分一致対応）
     */
    private static function find_model_key(string $model, array $pricing): ?string {
        // 完全一致
        if (isset($pricing[$model])) {
            return $model;
        }

        // 部分一致（プレフィックスマッチ）
        foreach (array_keys($pricing) as $key) {
            if (strpos($model, $key) === 0 || strpos($key, $model) === 0) {
                return $key;
            }
        }

        return null;
    }

    /**
     * 使用量統計を取得
     */
    public static function get_usage_stats(int $days = 30): array {
        global $wpdb;
        $table = wpaic_require_table('aichat_messages', 'get_usage_stats');
        if (!$table) {
            return ['daily_stats' => [], 'model_totals' => [], 'totals' => ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0, 'message_count' => 0, 'cost' => 0, 'cost_formatted' => '$0.00'], 'days' => $days];
        }

        // 日別の使用量を取得
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $daily_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) as date,
                ai_model,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(tokens_used) as total_tokens,
                COUNT(*) as message_count
             FROM {$table}
             WHERE role = 'assistant'
               AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(created_at), ai_model
             ORDER BY date ASC",
            $days
        ), ARRAY_A);

        // モデル別の合計
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $model_totals = $wpdb->get_results($wpdb->prepare(
            "SELECT
                ai_model,
                ai_provider,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(tokens_used) as total_tokens,
                COUNT(*) as message_count
             FROM {$table}
             WHERE role = 'assistant'
               AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY ai_model, ai_provider
             ORDER BY total_tokens DESC",
            $days
        ), ARRAY_A);

        // 全体の合計
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(tokens_used) as total_tokens,
                COUNT(*) as message_count
             FROM {$table}
             WHERE role = 'assistant'
               AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ), ARRAY_A);

        // コストを計算
        $total_cost = 0;
        $model_costs = [];

        foreach ($model_totals as &$model) {
            $model_name = $model['ai_model'] ?? 'unknown';
            $input_tokens = (int)($model['input_tokens'] ?? 0);
            $output_tokens = (int)($model['output_tokens'] ?? 0);

            // 入力/出力トークンがない場合（古いデータ）は推定
            if ($input_tokens === 0 && $output_tokens === 0 && !empty($model['total_tokens'])) {
                // 推定比率: 入力70%、出力30%
                $total = (int)$model['total_tokens'];
                $input_tokens = (int)($total * 0.7);
                $output_tokens = (int)($total * 0.3);
            }

            $cost = self::calculate_cost($model_name, $input_tokens, $output_tokens);
            $model['cost'] = $cost;
            $model['cost_formatted'] = self::format_cost($cost);
            $total_cost += $cost;
        }

        return [
            'daily_stats'  => $daily_stats,
            'model_totals' => $model_totals,
            'totals'       => [
                'input_tokens'  => (int)($totals['input_tokens'] ?? 0),
                'output_tokens' => (int)($totals['output_tokens'] ?? 0),
                'total_tokens'  => (int)($totals['total_tokens'] ?? 0),
                'message_count' => (int)($totals['message_count'] ?? 0),
                'cost'          => $total_cost,
                'cost_formatted' => self::format_cost($total_cost),
            ],
            'days'         => $days,
        ];
    }

    /**
     * コストをフォーマット
     */
    public static function format_cost(float $cost): string {
        if ($cost < 0.01) {
            return '$' . number_format($cost, 4);
        } elseif ($cost < 1) {
            return '$' . number_format($cost, 3);
        } else {
            return '$' . number_format($cost, 2);
        }
    }

    /**
     * 日本円に変換（おおよその換算レート）
     */
    public static function convert_to_jpy(float $cost, float $rate = 150): float {
        return $cost * $rate;
    }

    /**
     * 日本円でフォーマット
     */
    public static function format_cost_jpy(float $cost, float $rate = 150): string {
        $jpy = self::convert_to_jpy($cost, $rate);
        if ($jpy < 1) {
            return '¥' . number_format($jpy, 2);
        } else {
            return '¥' . number_format($jpy, 0);
        }
    }

    /**
     * 使用量をリセット（メッセージ履歴は残すがトークン情報をクリア）
     * バッチ処理でID範囲ごとに更新し、大量データでもテーブルロックを最小化する。
     */
    public static function reset_usage_stats(): bool {
        global $wpdb;
        $table = wpaic_require_table('aichat_messages', 'reset_usage_stats');
        if (!$table) {
            return false;
        }
        $batch_size = 5000;

        // トークン情報が残っている行の最大IDを取得（全件スキャン回避）
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $max_id = (int) $wpdb->get_var(
            "SELECT MAX(id) FROM {$table} WHERE tokens_used > 0 OR input_tokens > 0 OR output_tokens > 0"
        );

        if ($max_id <= 0) {
            return true;
        }

        // ID範囲でバッチ更新（各UPDATEは最大 $batch_size 行のみロック）
        for ($start = 1; $start <= $max_id; $start += $batch_size) {
            $end = $start + $batch_size - 1;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET tokens_used = 0, input_tokens = 0, output_tokens = 0 WHERE id BETWEEN %d AND %d",
                $start,
                $end
            ));
            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * 日別のグラフ用データを取得
     */
    public static function get_chart_data(int $days = 30): array {
        global $wpdb;
        $table = wpaic_require_table('aichat_messages', 'get_chart_data');
        if (!$table) {
            return [];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE(created_at) as date,
                SUM(input_tokens) as input_tokens,
                SUM(output_tokens) as output_tokens,
                SUM(tokens_used) as total_tokens
             FROM {$table}
             WHERE role = 'assistant'
               AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            $days
        ), ARRAY_A);

        $labels = [];
        $input_data = [];
        $output_data = [];
        $cost_data = [];

        // 日付範囲を埋める
        $start = new DateTime("-{$days} days");
        $end = new DateTime();
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end);

        $results_by_date = [];
        foreach ($results as $row) {
            $results_by_date[$row['date']] = $row;
        }

        foreach ($period as $date) {
            $date_str = $date->format('Y-m-d');
            $labels[] = $date->format('m/d');

            if (isset($results_by_date[$date_str])) {
                $row = $results_by_date[$date_str];
                $input = (int)($row['input_tokens'] ?? 0);
                $output = (int)($row['output_tokens'] ?? 0);

                // 入力/出力がない場合は推定
                if ($input === 0 && $output === 0 && !empty($row['total_tokens'])) {
                    $total = (int)$row['total_tokens'];
                    $input = (int)($total * 0.7);
                    $output = (int)($total * 0.3);
                }

                $input_data[] = $input;
                $output_data[] = $output;
                // 平均的なコスト（gpt-4o-miniベース）
                $cost_data[] = round(self::calculate_cost('gpt-4o-mini', $input, $output), 4);
            } else {
                $input_data[] = 0;
                $output_data[] = 0;
                $cost_data[] = 0;
            }
        }

        return [
            'labels'      => $labels,
            'input_data'  => $input_data,
            'output_data' => $output_data,
            'cost_data'   => $cost_data,
        ];
    }
}
