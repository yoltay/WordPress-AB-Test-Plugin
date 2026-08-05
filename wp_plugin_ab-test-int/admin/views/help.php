<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap abti-wrap abti-help-page">
    <h1><?php esc_html_e( 'A/B Test int Nasıl Çalışır?', 'ab-test-int' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Elementor varyasyonlarını yayınlama, ölçme ve güvenli biçimde güncelleme rehberi.', 'ab-test-int' ); ?></p>

    <div class="abti-help-grid">
        <section class="abti-help-section">
            <h2><?php esc_html_e( '1. Varyasyonları hazırlayın', 'ab-test-int' ); ?></h2>
            <ol>
                <li><?php esc_html_e( 'Elementor’da test edeceğiniz section veya container’ı kopyalayın.', 'ab-test-int' ); ?></li>
                <li><?php esc_html_e( 'Yeni bir test oluşturun ve her varyasyon için üretilen CSS ID değerini kopyalayın.', 'ab-test-int' ); ?></li>
                <li><?php esc_html_e( 'Değeri # işareti olmadan Elementor > Advanced > CSS ID alanına yapıştırın.', 'ab-test-int' ); ?></li>
            </ol>
            <p class="abti-help-warning"><strong><?php esc_html_e( 'Önemli:', 'ab-test-int' ); ?></strong> <?php esc_html_e( 'Test edilen elementlerde Elementor Responsive > Hide ayarını kullanmayın. Görünürlüğü eklenti yönetir.', 'ab-test-int' ); ?></p>
        </section>

        <section class="abti-help-section">
            <h2><?php esc_html_e( '2. Trafik ve hedef', 'ab-test-int' ); ?></h2>
            <p><?php esc_html_e( 'Varyasyon yüzdelerinin toplamı 100 olmalıdır. Sunucu kotası, yeni tarayıcı atamalarını hedef yüzdelerin en gerisindeki varyasyona yönlendirir. Değerler eşitse adaylar deterministik sırayla döndürülür.', 'ab-test-int' ); ?></p>
            <p><?php esc_html_e( 'Hedef olarak bir CSS selector’a tıklamayı veya form gönderimini seçebilirsiniz. Form selector’ı boşsa sayfadaki tüm form gönderimleri ölçülür.', 'ab-test-int' ); ?></p>
        </section>

        <section class="abti-help-section">
            <h2><?php esc_html_e( '3. Ziyaretçi ataması', 'ab-test-int' ); ?></h2>
            <p><?php esc_html_e( 'Atama tarayıcı profiline kaydedilir. Chrome ve Firefox ayrı ziyaretçi sayılır. Gizli pencere kapatıldığında kayıt silinir ve yeni gizli oturumda yeni atama yapılır.', 'ab-test-int' ); ?></p>
            <p><?php esc_html_e( 'İlk ziyarette picker, body çizilmeden önce sunucudan kota dengeli atamayı alır. Daha sonraki ziyaretlerde kayıtlı atama ağ isteği olmadan uygulanır.', 'ab-test-int' ); ?></p>
        </section>

        <section class="abti-help-section">
            <h2><?php esc_html_e( '4. Cache ve WP Rocket', 'ab-test-int' ); ?></h2>
            <p><?php esc_html_e( 'Picker, gizleme CSS’i ve atama endpoint’i minify, defer, delay ve Used CSS işlemlerinden dışlanır. Ana sayfa cache’lenebilir; atama cevabı no-store başlıklarıyla dinamik kalır.', 'ab-test-int' ); ?></p>
            <p class="abti-help-warning"><strong><?php esc_html_e( 'Her test değişikliğinden sonra:', 'ab-test-int' ); ?></strong> <?php esc_html_e( 'WP Rocket sayfa önbelleğini ve Used CSS verisini temizleyin. Cloudflare kullanıyorsanız ilgili sayfa cache’ini de temizleyin.', 'ab-test-int' ); ?></p>
        </section>

        <section class="abti-help-section">
            <h2><?php esc_html_e( '5. İstatistikler ve sıfırlama', 'ab-test-int' ); ?></h2>
            <p><?php esc_html_e( 'Görüntülenme ve dönüşümler REST API üzerinden kaydedilir. İstatistikleri sıfırlama işlemi yalnızca event kayıtlarını siler; test ayarları, CSS ID’leri ve kalıcı kota atamaları korunur.', 'ab-test-int' ); ?></p>
        </section>

        <section class="abti-help-section">
            <h2><?php esc_html_e( '6. Eklentiyi güncelleme', 'ab-test-int' ); ?></h2>
            <p><?php esc_html_e( 'WordPress > Eklentiler > Eklenti Ekle > Eklenti Yükle yoluyla yeni ZIP’i seçin ve mevcut sürümle değiştirmeyi onaylayın. Migration mevcut test ve event tablolarını silmeden yalnızca gerekli yeni tabloyu ekler.', 'ab-test-int' ); ?></p>
        </section>
    </div>
</div>
