=== A/B Test int ===
Contributors: sedat
Tags: ab-testing, split-testing, elementor, conversion-optimization
Requires at least: 5.6
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.2.0

Elementor ile tasarlanmış sayfalar için basit A/B testi.

== Açıklama ==

Bu eklenti, sayfanızda bir element/section/container'ın iki (veya daha fazla, en fazla 5) varyasyonunu eş zamanlı yayınlamanıza ve dönüşüm oranlarını ölçmenize yarar.

= Nasıl çalışır =

1. Elementor'da test etmek istediğiniz section/container/element'i kopyalayın.
2. WP Admin > A/B Test int > "+ Test Ekle" deyin.
3. Eklentinin üreteceği CSS ID/Class adını (örn. `a-fe46yhs2`) Elementor "Advanced > CSS ID" veya "Advanced > CSS Classes" alanına `#` veya `.` işareti olmadan yapıştırın.
4. Hedef davranışı (tıklama veya form submit) ve ölçümlenecek selector'ı girin.
5. Kaydedin. Sayfa frontend'inde her ziyaretçiye yüzdelere göre random bir varyasyon gösterilir.

= Önemli notlar =

* Cache eklentileri (WP Rocket, Autoptimize, LiteSpeed Cache, WP Fastest Cache, SG Optimizer) için otomatik exclude filtreleri eklenmiştir.
* Test oluşturduktan/güncelledikten sonra ilgili sayfanın page cache'ini temizleyin.
* WP Rocket "Delay JavaScript", "Load JavaScript deferred" ve "Remove Unused CSS" gibi optimizasyonlar kullanılıyorsa eklenti picker script'ini ve inline gizleme CSS'ini otomatik dışarıda bırakmaya çalışır. Buna rağmen sorun görürseniz ilgili sayfa cache/CDN cache'ini temizleyin.
* Tracking REST API üzerinden çalışır (`/wp-json/abti/v1/track`) — bu endpoint zaten cache dışıdır.

== Changelog ==

= 1.2.0 =
* Hata düzeltme: Cache/minify eklentileri `id="abti-hide-all"` attribute'unu kaldırdığında picker yeni bir `<style>` oluşturuyordu; orijinal style DOM'da kalıp her iki varyasyonu da gizliyordu. Artık `data-abti` attribute'u ve `previousElementSibling` ile orijinal style güvenilir şekilde bulunup güncelleniyor.
* Hata düzeltme: Picker JS'de try-catch yoktu; beklenmedik bir hata oluştuğunda CSS tüm elementleri gizli bırakıyordu. Artık herhangi bir istisna yakalanıyor.
* Davranış: Picker başarısız olsa bile kullanıcıya iki element aynı anda gösterilmiyor; PHP fallback'i (index-0 varyasyonu görünür) devreye giriyor.
* Güvenilirlik: Varyasyon `key` alanı eksikse o test güvenli şekilde atlanıyor, diğer testler etkilenmiyor.

= 1.1.1 =
* Sağlamlık: Picker script'i optimizasyon eklentileri tarafından geciktirilirse sayfa boş kalmasın diye ilk varyasyon CSS fallback olarak görünür bırakıldı.
* Uyumluluk: WP Rocket, LiteSpeed Cache, Autoptimize ve SG Optimizer için inline JS/CSS exclude imzaları genişletildi.
* Admin: Elementor CSS ID/Class alanlarına yapıştırmak için kopyalama artık `#` / `.` prefix'i olmadan yapılır.

= 1.1.0 =
* Performans: Varyasyon seçimi artık head içinde, body parse edilmeden önce inline çalışıyor. Eski versiyonda DOMContentLoaded sonrası olan "geç beliren element" sorunu giderildi.
* Stats sayfasına küçük ve onaylı "İstatistik verilerini sıfırla" butonu eklendi (test ayarlarına dokunmaz).
* Tracking script'i artık footer'da defer ile yükleniyor.

= 1.0.0 =
* İlk sürüm.
