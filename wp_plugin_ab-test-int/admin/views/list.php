<?php
/**
 * @var array $tests
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$new_url = add_query_arg( array( 'page' => 'abti-tests-new' ), admin_url( 'admin.php' ) );
?>
<div class="wrap abti-wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'A/B Test int', 'ab-test-int' ); ?></h1>
    <a href="<?php echo esc_url( $new_url ); ?>" class="page-title-action">+ <?php esc_html_e( 'Test Ekle', 'ab-test-int' ); ?></a>
    <hr class="wp-header-end" />

    <?php if ( ! empty( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Test silindi.', 'ab-test-int' ); ?></p></div>
    <?php endif; ?>

    <?php if ( empty( $tests ) ) : ?>
        <div class="abti-empty">
            <p><?php esc_html_e( 'Henüz bir test oluşturmadın.', 'ab-test-int' ); ?></p>
            <a href="<?php echo esc_url( $new_url ); ?>" class="button button-primary">+ <?php esc_html_e( 'İlk testini ekle', 'ab-test-int' ); ?></a>
        </div>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped abti-table">
        <thead>
            <tr>
                <th style="width:60px;">#</th>
                <th><?php esc_html_e( 'Test Adı', 'ab-test-int' ); ?></th>
                <th><?php esc_html_e( 'Sayfa', 'ab-test-int' ); ?></th>
                <th><?php esc_html_e( 'Varyasyon', 'ab-test-int' ); ?></th>
                <th><?php esc_html_e( 'Hedef', 'ab-test-int' ); ?></th>
                <th><?php esc_html_e( 'Durum', 'ab-test-int' ); ?></th>
                <th style="width:240px;"><?php esc_html_e( 'İşlemler', 'ab-test-int' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $tests as $t ) :
                $variations = json_decode( $t->variations, true );
                $count      = is_array( $variations ) ? count( $variations ) : 0;
                $page_title = $t->page_id ? get_the_title( $t->page_id ) : __( '— sayfa seçilmedi —', 'ab-test-int' );
                $edit_url   = add_query_arg( array( 'page' => 'abti-tests', 'action' => 'edit', 'id' => $t->id ), admin_url( 'admin.php' ) );
                $stats_url  = add_query_arg( array( 'page' => 'abti-tests', 'action' => 'stats', 'id' => $t->id ), admin_url( 'admin.php' ) );
                $delete_url = wp_nonce_url(
                    add_query_arg( array( 'page' => 'abti-tests', 'action' => 'delete', 'id' => $t->id ), admin_url( 'admin.php' ) ),
                    'abti_delete_' . $t->id
                );
                ?>
                <tr>
                    <td><?php echo (int) $t->id; ?></td>
                    <td><strong><?php echo esc_html( $t->name ); ?></strong></td>
                    <td><?php echo esc_html( $page_title ); ?></td>
                    <td><?php echo (int) $count; ?></td>
                    <td>
                        <?php if ( $t->goal_type === 'click' ) : ?>
                            <span class="abti-pill abti-pill-click"><?php esc_html_e( 'Tıklama', 'ab-test-int' ); ?></span>
                        <?php else : ?>
                            <span class="abti-pill abti-pill-form"><?php esc_html_e( 'Form Submit', 'ab-test-int' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( (int) $t->status === 1 ) : ?>
                            <span class="abti-status abti-status-on"><?php esc_html_e( 'Aktif', 'ab-test-int' ); ?></span>
                        <?php else : ?>
                            <span class="abti-status abti-status-off"><?php esc_html_e( 'Pasif', 'ab-test-int' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'ab-test-int' ); ?></a>
                        <a href="<?php echo esc_url( $stats_url ); ?>" class="button button-small"><?php esc_html_e( 'Stats', 'ab-test-int' ); ?></a>
                        <a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small abti-delete" data-confirm="1"><?php esc_html_e( 'Delete', 'ab-test-int' ); ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
