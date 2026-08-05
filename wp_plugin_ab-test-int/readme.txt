=== A/B Test int ===
Contributors: sedat
Tags: ab-testing, split-testing, elementor, conversion-optimization
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.3.1

Elementor ile tasarlanmış sayfalar için tarayıcı bazlı, kota dengeli A/B testi.

== Açıklama ==

Bu eklenti, sayfanızda bir element/section/container'ın iki veya daha fazla (en fazla 5) varyasyonunu yayınlamanıza ve dönüşüm oranlarını ölçmenize yarar.

= Nasıl çalışır =

1. Elementor'da test etmek istediğiniz section/container/element'i kopyalayın.
2. WP Admin > A/B Test int > Yeni Test yoluyla testi oluşturun.
3. Üretilen CSS ID/Class adını Elementor Advanced > CSS ID veya CSS Classes alanına # veya . işareti olmadan yapıştırın.
4. Varyasyon yüzdelerini toplam 100 olacak şekilde ayarlayın.
5. Hedef davranışı (tıklama veya form submit) ve gerekiyorsa CSS selector'ı girin.
6. İlk ziyarette head içindeki picker sunucudan kota dengeli atamayı alır; sonraki ziyaretlerde aynı tarayıcı kayıtlı varyasyonu anında kullanır.

= Atama ve dengeleme =

* Atamalar tarayıcı profiline özeldir. Aynı cihazdaki Chrome ve Firefox ayrı ziyaretçi sayılır.
* Gizli pencere kapatılıp yeni bir gizli oturum açıldığında yeni ziyaretçi ve atama oluşur.
* v1.3 anahtarları abti_v3_ öneki kullanır; v1.2 localStorage kayıtları dikkate alınmaz.
* Sunucu, her varyasyonun hedef oranı ile mevcut kalıcı atama sayısını karşılaştırır ve hedefin en gerisindeki varyasyonu seçer.
* Kota açıkları eşitse adaylar toplam atama sayısına göre deterministik olarak döndürülür; her zaman A seçilmez.
* Aynı testte eş zamanlı ilk ziyaretlerin dağılımı bozmasını azaltmak için kısa süreli MySQL named lock kullanılır.

= Önemli notlar =

* Test elementlerinde Elementor Responsive > Hide ayarını kullanmayın. Görünürlüğü eklenti yönetir.
* WP Rocket, Autoptimize, LiteSpeed Cache, WP Fastest Cache ve SG Optimizer için otomatik exclude filtreleri vardır.
* Picker ve inline gizleme CSS'i WP Rocket minify, defer, delay ve Remove Unused CSS işlemlerinden dışlanır.
* Atama endpoint'i (/wp-json/abti/v1/assign) no-store başlıkları döndürür. Ana sayfa cache'lenebilir, atama cevabı cache'lenmez.
* Test oluşturduktan veya güncelledikten sonra WP Rocket sayfa cache'ini ve Used CSS verisini temizleyin. CDN/Cloudflare cache'i varsa ilgili sayfayı orada da temizleyin.
* İstatistik sıfırlama yalnızca view/conversion event'lerini siler; test ayarları, CSS ID'leri ve kalıcı atamalar korunur.

= Silmeden güncelleme =

WordPress Admin > Eklentiler > Eklenti Ekle > Eklenti Yükle yoluyla yeni ZIP'i yükleyin ve mevcut eklentiyi yenisiyle değiştirmeyi onaylayın. v1.3 migration rutini mevcut abti_tests ve abti_events tablolarına dokunmadan abti_assignments tablosunu ekler.

== Changelog ==

= 1.3.1 =
* WP Rocket Remove Unused CSS ile uyumluluk guclendirildi; ABTI gizleme CSS'i Used CSS bloguna kopyalanmaz.
* Eski cache'ten kalmis WP Rocket Used CSS icindeki ABTI display:none kurallari picker tarafindan temizlenir.
* Inline hide style icin v1.3.1'e ozel DOM marker'i eklendi. DB, test kayitlari ve varyasyon CSS ID/Class degerleri korunur.


= 1.3.0 =
* Tarayıcı bazlı storage anahtarları abti_v3_ önekine taşındı; eski picker kayıtları yok sayılıyor.
* Kalıcı abti_assignments tablosu ve upload ile güncellemede çalışan additive dbDelta migration eklendi.
* İlk ziyaret ataması sunucu taraflı hedef-açığı kotasıyla ve body render edilmeden önce yapılıyor.
* Eşit kota açıklarında deterministik rotasyon, eş zamanlı atamalarda test-bazlı MySQL lock eklendi.
* Atama REST cevabı cache dışı bırakıldı; WP Rocket minify/defer/delay/Used CSS exclude imzaları genişletildi.
* Admin'e Nasıl Çalışır sayfası ve test listesine rehber bağlantısı eklendi.
* Plugin header ve asset sürümü 1.3.0 olarak eşitlendi.

= 1.2.0 =
* Cache/minify eklentileri style ID'sini değiştirse bile data-abti ile gizleme style'ı güvenilir biçimde bulunuyor.
* Picker hatasında PHP index-0 fallback'i sayfanın boş kalmasını önlüyor.

= 1.1.1 =
* Picker ve inline CSS için cache/optimizasyon exclude kapsamı genişletildi.

= 1.1.0 =
* Varyasyon seçimi head içinde body parse edilmeden çalışacak şekilde taşındı.
* İstatistik verilerini sıfırlama eklendi.

= 1.0.0 =
* İlk sürüm.
