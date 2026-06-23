<?php
/**
 * @var object $test
 * @var array  $stats         (key => [name, percentage, views, conversions, rate, rank])
 * @var array  $chart_payload (labels, variations, conversions, views, stats)
 * @var string $start
 * @var string $end
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$page_title = $test ? sprintf( __( 'Stats — %s', 'ab-test-int' ), $test->name ) : __( 'Stats', 'ab-test-int' );

// Tablo için sıralı liste.
$ordered_stats = $stats;
uasort( $ordered_stats, function ( $a, $b ) { return $a['key'] <=> $b['key']; } );

$reset_url = wp_nonce_url(
    add_query_arg(
        array( 'page' => 'abti-tests', 'action' => 'reset_stats', 'id' => $test->id ),
        admin_url( 'admin.php' )
    ),
    'abti_reset_' . $test->id
);
?>
<div class="wrap abti-wrap abti-stats">
    <h1>
        <?php echo esc_html( $page_title ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=abti-tests' ) ); ?>" class="page-title-action"><?php esc_html_e( '← Listeye dön', 'ab-test-int' ); ?></a>
    </h1>

    <?php if ( ! empty( $_GET['reset'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'İstatistikler sıfırlandı.', 'ab-test-int' ); ?></p></div>
    <?php endif; ?>

    <h2 class="abti-stats-section-title"><?php esc_html_e( 'Split Testing', 'ab-test-int' ); ?></h2>

    <!-- Üst kartlar -->
    <div class="abti-cards">
        <?php foreach ( $stats as $row ) :
            $is_winner = ( $row['rank'] === 1 && $row['rate'] > 0 );
            ?>
            <div class="abti-card <?php echo $is_winner ? 'abti-card-winner' : 'abti-card-loser'; ?>">
                <div class="abti-card-name"><?php echo esc_html( $row['name'] ); ?></div>
                <div class="abti-card-row">
                    <div class="abti-card-rate"><?php echo esc_html( number_format_i18n( $row['rate'], 1 ) ); ?>%</div>
                    <div class="abti-card-rank">#<?php echo (int) $row['rank']; ?></div>
                </div>
                <div class="abti-card-bar"><span style="width: <?php echo $row['rate'] > 0 ? min( 100, $row['rate'] * 5 ) : 5; ?>%;"></span></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Orta: line chart + sağda timerange + donut -->
    <div class="abti-grid">
        <div class="abti-card-large abti-chart-wrap">
            <canvas id="abti-line-chart" height="120"></canvas>
        </div>

        <div class="abti-side">
            <div class="abti-card-large abti-timerange">
                <h3><?php esc_html_e( 'Tarih Aralığı', 'ab-test-int' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Analiz periyodunu seçin', 'ab-test-int' ); ?></p>
                <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="abti-timerange-form">
                    <input type="hidden" name="page" value="abti-tests" />
                    <input type="hidden" name="action" value="stats" />
                    <input type="hidden" name="id" value="<?php echo (int) $test->id; ?>" />
                    <div class="abti-tr-row">
                        <label><?php esc_html_e( 'Başlangıç', 'ab-test-int' ); ?></label>
                        <input type="date" name="start" value="<?php echo esc_attr( $start ); ?>" />
                    </div>
                    <div class="abti-tr-row">
                        <label><?php esc_html_e( 'Bitiş', 'ab-test-int' ); ?></label>
                        <input type="date" name="end" value="<?php echo esc_attr( $end ); ?>" />
                    </div>
                    <button type="submit" class="button button-primary abti-tr-submit"><?php esc_html_e( 'Yükle', 'ab-test-int' ); ?></button>
                </form>
            </div>

            <div class="abti-card-large abti-donut-wrap">
                <canvas id="abti-donut-chart" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Tablo -->
    <div class="abti-card-large abti-stats-table-wrap">
        <table class="wp-list-table widefat fixed striped abti-stats-table">
            <thead>
                <tr>
                    <th></th>
                    <th><?php esc_html_e( 'Varyasyon', 'ab-test-int' ); ?></th>
                    <th><?php esc_html_e( 'Yüzde', 'ab-test-int' ); ?></th>
                    <th><?php esc_html_e( 'Görüntüleme', 'ab-test-int' ); ?></th>
                    <th><?php esc_html_e( 'Dönüşüm', 'ab-test-int' ); ?></th>
                    <th><?php esc_html_e( 'Dönüşüm Oranı', 'ab-test-int' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $ordered_stats as $row ) : ?>
                    <tr>
                        <td><span class="abti-color-dot" data-key="<?php echo esc_attr( $row['key'] ); ?>"></span></td>
                        <td><?php echo esc_html( $row['name'] ); ?></td>
                        <td><?php echo (int) $row['percentage']; ?>%</td>
                        <td><?php echo (int) $row['views']; ?></td>
                        <td><?php echo (int) $row['conversions']; ?></td>
                        <td><?php echo esc_html( number_format_i18n( $row['rate'], 1 ) ); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="abti-reset-row">
        <a href="<?php echo esc_url( $reset_url ); ?>" class="abti-reset-link" data-confirm="1">
            <span aria-hidden="true">↺</span>
            <?php esc_html_e( 'İstatistik verilerini sıfırla', 'ab-test-int' ); ?>
        </a>
    </p>

    <script type="application/json" id="abti-chart-payload"><?php echo wp_json_encode( $chart_payload ); ?></script>
</div>
