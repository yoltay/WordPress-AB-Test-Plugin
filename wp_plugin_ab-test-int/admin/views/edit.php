<?php
/**
 * @var WP_Post|object|null $test
 * @var array $variations
 * @var array $pages_dropdown
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$is_edit       = ! empty( $test );
$page_title    = $is_edit ? __( 'Testi Düzenle', 'ab-test-int' ) : __( 'Yeni Test', 'ab-test-int' );
$test_name     = $is_edit ? $test->name : '';
$page_id_val   = $is_edit ? (int) $test->page_id : 0;
$status_val    = $is_edit ? (int) $test->status : 1;
$goal_type     = $is_edit ? $test->goal_type : 'click';
$goal_selector = $is_edit ? $test->goal_selector : '';

// JS template için boş varyasyon (a-e için pre-generated isimler).
$blank_template = array();
foreach ( array( 'a', 'b', 'c', 'd', 'e' ) as $k ) {
    $blank_template[ $k ] = ABTI_Admin::blank_variation( $k );
}
?>
<div class="wrap abti-wrap">
    <h1><?php echo esc_html( $page_title ); ?></h1>

    <?php if ( ! empty( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Kaydedildi.', 'ab-test-int' ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=abti-tests' ) ); ?>" class="abti-form">
        <?php wp_nonce_field( 'abti_save_test' ); ?>
        <input type="hidden" name="abti_action" value="save_test" />
        <input type="hidden" name="test_id" value="<?php echo (int) ( $is_edit ? $test->id : 0 ); ?>" />

        <table class="form-table">
            <tr>
                <th scope="row"><label for="abti-name"><?php esc_html_e( 'Test Adı', 'ab-test-int' ); ?></label></th>
                <td>
                    <input type="text" name="name" id="abti-name" class="regular-text" required value="<?php echo esc_attr( $test_name ); ?>" placeholder="Örn. Anasayfa CTA Renk Testi" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="abti-page"><?php esc_html_e( 'Sayfa', 'ab-test-int' ); ?></label></th>
                <td>
                    <select name="page_id" id="abti-page" required>
                        <option value=""><?php esc_html_e( 'Seçiniz...', 'ab-test-int' ); ?></option>
                        <?php foreach ( $pages_dropdown as $group_label => $group_items ) : ?>
                            <optgroup label="<?php echo esc_attr( $group_label ); ?>">
                                <?php foreach ( $group_items as $id => $title ) : ?>
                                    <option value="<?php echo (int) $id; ?>" <?php selected( $page_id_val, $id ); ?>><?php echo esc_html( $title ); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label><?php esc_html_e( 'Durum', 'ab-test-int' ); ?></label></th>
                <td>
                    <label><input type="checkbox" name="status" value="1" <?php checked( $status_val, 1 ); ?> /> <?php esc_html_e( 'Test aktif', 'ab-test-int' ); ?></label>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Varyasyonlar', 'ab-test-int' ); ?></h2>
        <p class="description">
            <?php esc_html_e( 'Aşağıdaki CSS ID/Class isimlerini Elementor\'da ilgili element/section/container\'ın "Advanced > CSS ID" veya "Advanced > CSS Classes" alanına # veya . işareti olmadan yapıştırın. Eklenti, sadece seçilen varyasyonu görünür kılar.', 'ab-test-int' ); ?>
        </p>

        <div id="abti-variations" class="abti-variations">
            <?php foreach ( $variations as $i => $v ) :
                $key   = $v['key'];
                $name  = $v['name'];
                $sel   = $v['selector'];
                $type  = isset( $v['selector_type'] ) ? $v['selector_type'] : 'id';
                $perc  = isset( $v['percentage'] ) ? (int) $v['percentage'] : 0;
                ?>
                <div class="abti-variation" data-key="<?php echo esc_attr( $key ); ?>">
                    <div class="abti-variation-head">
                        <span class="abti-variation-letter"><?php echo esc_html( strtoupper( $key ) ); ?></span>
                        <input type="text" class="abti-vname" name="variations[<?php echo (int) $i; ?>][name]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php esc_attr_e( 'Varyasyon adı', 'ab-test-int' ); ?>" />
                        <button type="button" class="button abti-remove-var" title="<?php esc_attr_e( 'Bu varyasyonu kaldır', 'ab-test-int' ); ?>">×</button>
                    </div>
                    <div class="abti-variation-body">
                        <input type="hidden" name="variations[<?php echo (int) $i; ?>][key]" value="<?php echo esc_attr( $key ); ?>" />

                        <div class="abti-field">
                            <label><?php esc_html_e( 'Selector tipi', 'ab-test-int' ); ?></label>
                            <select name="variations[<?php echo (int) $i; ?>][selector_type]" class="abti-vtype">
                                <option value="id" <?php selected( $type, 'id' ); ?>><?php esc_html_e( 'CSS ID (#)', 'ab-test-int' ); ?></option>
                                <option value="class" <?php selected( $type, 'class' ); ?>><?php esc_html_e( 'CSS Class (.)', 'ab-test-int' ); ?></option>
                            </select>
                        </div>

                        <div class="abti-field abti-field-grow">
                            <label><?php esc_html_e( 'CSS ID / Class', 'ab-test-int' ); ?></label>
                            <div class="abti-selector-wrap">
                                <span class="abti-selector-prefix"><?php echo $type === 'class' ? '.' : '#'; ?></span>
                                <input type="text" class="abti-vselector" name="variations[<?php echo (int) $i; ?>][selector]" value="<?php echo esc_attr( $sel ); ?>" />
                                <button type="button" class="button abti-regen" title="<?php esc_attr_e( 'Yeniden üret', 'ab-test-int' ); ?>">↻</button>
                                <button type="button" class="button abti-copy" title="<?php esc_attr_e( 'Kopyala', 'ab-test-int' ); ?>">⧉</button>
                            </div>
                        </div>

                        <div class="abti-field abti-field-narrow">
                            <label><?php esc_html_e( 'Gösterim %', 'ab-test-int' ); ?></label>
                            <input type="number" min="0" max="100" step="1" class="abti-vperc" name="variations[<?php echo (int) $i; ?>][percentage]" value="<?php echo (int) $perc; ?>" />
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <p>
            <button type="button" class="button" id="abti-add-variation">+ <?php esc_html_e( 'Varyasyon Ekle', 'ab-test-int' ); ?></button>
            <button type="button" class="button" id="abti-balance">⚖ <?php esc_html_e( 'Yüzdeleri Eşitle', 'ab-test-int' ); ?></button>
            <span class="abti-perc-summary"><?php esc_html_e( 'Toplam:', 'ab-test-int' ); ?> <strong id="abti-perc-total">0</strong>%</span>
        </p>

        <h2><?php esc_html_e( 'Ölçümlenecek Davranış', 'ab-test-int' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="abti-goal-type"><?php esc_html_e( 'Davranış', 'ab-test-int' ); ?></label></th>
                <td>
                    <select name="goal_type" id="abti-goal-type">
                        <option value="click" <?php selected( $goal_type, 'click' ); ?>><?php esc_html_e( 'Tıklama', 'ab-test-int' ); ?></option>
                        <option value="form_submit" <?php selected( $goal_type, 'form_submit' ); ?>><?php esc_html_e( 'Form Submit', 'ab-test-int' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="abti-goal-selector"><?php esc_html_e( 'Hedef Selector', 'ab-test-int' ); ?></label></th>
                <td>
                    <input type="text" name="goal_selector" id="abti-goal-selector" class="regular-text" value="<?php echo esc_attr( $goal_selector ); ?>" placeholder=".my-cta-button or #signup-form" />
                    <p class="description abti-goal-hint-click">
                        <?php esc_html_e( 'Tıklama: ölçümlenecek butonun/elementin CSS class\'ını (".class-adi") veya ID\'sini ("#id-adi") yazın.', 'ab-test-int' ); ?>
                    </p>
                    <p class="description abti-goal-hint-form" style="display:none;">
                        <?php esc_html_e( 'Form Submit: izlenecek formun selector\'ını yazın. Boş bırakırsanız sayfadaki tüm form submit\'leri sayılır. Elementor Forms ve Contact Form 7 popup formları otomatik yakalanır.', 'ab-test-int' ); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" class="button button-primary"><?php echo $is_edit ? esc_html__( 'Güncelle', 'ab-test-int' ) : esc_html__( 'Testi Kaydet', 'ab-test-int' ); ?></button>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=abti-tests' ) ); ?>" class="button"><?php esc_html_e( 'İptal', 'ab-test-int' ); ?></a>
        </p>
    </form>

    <script type="application/json" id="abti-blank-template"><?php echo wp_json_encode( $blank_template ); ?></script>
</div>
