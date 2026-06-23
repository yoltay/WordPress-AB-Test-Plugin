<?php
/**
 * Frontend: sayfada testleri tespit eder, varyasyonları render eder ve
 * tracking script'ini enqueue eder.
 *
 * v1.1: Performans iyileştirmesi.
 *  - Varyasyon seçimi artık <head>'in içinde, body parse edilmeden önce inline
 *    olarak çalışır ("picker"). Picker, head'deki gizleme <style>'ının içeriğini
 *    yeniden yazıp SADECE seçili olmayan varyasyonları gizli bırakır. Böylece
 *    body parse edilirken seçili varyasyon hiç gizlenmemiş gibi akar; FOUC ve
 *    DOMContentLoaded sonrası "geç beliren element" davranışı ortadan kalkar.
 *  - JS'in optimize/defer edilmesi veya hata vermesi durumunda sayfa boş
 *    kalmasın diye CSS fallback ilk varyasyonu görünür bırakır.
 *  - Tracking script'i footer'da defer ile yüklenir (rendering'i bloklamaz).
 *
 * Cache eklentileri (WP Rocket / Autoptimize / LiteSpeed / WPFC / SG Optimizer /
 * Cloudflare Rocket Loader) için exclude filtreleri ve marker'lar eklenmiştir.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ABTI_Frontend {

    /** @var array */
    private $tests_for_page = array();

    public function __construct() {
        add_action( 'wp', array( $this, 'detect_tests' ) );
        // 1) Önce hide-all CSS'i.
        add_action( 'wp_head', array( $this, 'output_inline_hide_css' ), 1 );
        // 2) Hemen ardından inline picker. Body parse başlamadan çalışır.
        add_action( 'wp_head', array( $this, 'output_inline_picker' ), 2 );
        // 3) Tracking script footer'da.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        /* ---------- Cache / Minify exclude filters ---------- */
        add_filter( 'rocket_exclude_js', array( $this, 'rocket_exclude_js' ) );
        add_filter( 'rocket_excluded_inline_js_content', array( $this, 'rocket_excluded_inline' ) );
        add_filter( 'rocket_delay_js_exclusions', array( $this, 'rocket_delay_js_exclusions' ) );
        add_filter( 'rocket_defer_inline_exclusions', array( $this, 'rocket_defer_inline_exclusions' ) );
        add_filter( 'rocket_exclude_defer_js', array( $this, 'rocket_exclude_js' ) );
        add_filter( 'rocket_exclude_css', array( $this, 'rocket_exclude_css' ) );
        add_filter( 'rocket_rucss_inline_content_exclusions', array( $this, 'rocket_rucss_inline_content_exclusions' ) );

        add_filter( 'autoptimize_filter_js_exclude', array( $this, 'autoptimize_js_exclude' ) );
        add_filter( 'autoptimize_filter_css_exclude', array( $this, 'autoptimize_css_exclude' ) );

        add_filter( 'wpfc_exclude_js', array( $this, 'wpfc_exclude_js' ) );

        add_filter( 'litespeed_optm_js_exc', array( $this, 'litespeed_exclude' ) );
        add_filter( 'litespeed_optm_js_defer_exc', array( $this, 'litespeed_exclude' ) );
        add_filter( 'litespeed_optm_js_minify_exc', array( $this, 'litespeed_exclude' ) );
        add_filter( 'litespeed_optm_js_comb_exc', array( $this, 'litespeed_exclude' ) );
        add_filter( 'litespeed_optm_css_exc', array( $this, 'litespeed_css_exclude' ) );
        add_filter( 'litespeed_optm_css_minify_exc', array( $this, 'litespeed_css_exclude' ) );
        add_filter( 'litespeed_optm_css_comb_exc', array( $this, 'litespeed_css_exclude' ) );
        add_filter( 'litespeed_optm_ucss_exc', array( $this, 'litespeed_css_exclude' ) );

        add_filter( 'sgo_js_minify_exclude', array( $this, 'sgo_exclude' ) );
        add_filter( 'sgo_javascript_combine_exclude', array( $this, 'sgo_exclude' ) );
        add_filter( 'sgo_js_async_exclude', array( $this, 'sgo_exclude' ) );
        add_filter( 'sgo_css_minify_exclude', array( $this, 'sgo_css_exclude' ) );
        add_filter( 'sgo_css_combine_exclude', array( $this, 'sgo_css_exclude' ) );
    }

    /**
     * Sayfa render başlamadan önce mevcut sayfa için aktif testleri yükle.
     */
    public function detect_tests() {
        if ( is_admin() || ! is_singular() ) {
            return;
        }
        $page_id = get_queried_object_id();
        if ( ! $page_id ) {
            return;
        }
        $tests = ABTI_Database::get_active_tests_for_page( $page_id );

        $clean = array();
        foreach ( $tests as $t ) {
            $variations = json_decode( $t->variations, true );
            if ( ! is_array( $variations ) || empty( $variations ) ) {
                continue;
            }
            $clean[] = array(
                'id'            => (int) $t->id,
                'name'          => $t->name,
                'variations'    => $variations,
                'goal_type'     => $t->goal_type,
                'goal_selector' => $t->goal_selector,
            );
        }
        $this->tests_for_page = $clean;
    }

    /**
     * JS çalışmazsa sayfa boş kalmasın diye ilk varyasyonu fallback olarak
     * görünür bırak. Picker birazdan içeriği yeniden yazıp gerçek seçime göre
     * sadece seçili olmayanları gizli bırakacak.
     */
    public function output_inline_hide_css() {
        if ( empty( $this->tests_for_page ) ) {
            return;
        }
        $selectors = array();
        foreach ( $this->tests_for_page as $test ) {
            foreach ( $test['variations'] as $index => $v ) {
                if ( $index === 0 ) {
                    continue;
                }
                $sel = $this->build_selector( $v );
                if ( $sel ) {
                    $selectors[] = $sel;
                }
            }
        }
        if ( empty( $selectors ) ) {
            return;
        }

        echo "<!--noptimize-->\n";
        echo '<style id="abti-hide-all" data-abti="1" data-no-optimize="1" data-no-minify="1">';
        echo '/* abti-hide-all */';
        echo implode( ',', array_map( 'esc_html', $selectors ) );
        echo '{display:none !important;}';
        echo "</style>\n";
        echo "<!--/noptimize-->\n";
    }

    /**
     * Inline picker — head içinde, body parse başlamadan önce çalışır.
     * Varyasyonu seçer, hide-all <style>'ının içeriğini yeniden yazar.
     * Seçili varyasyon hiç gizlenmeden render edilir.
     */
    public function output_inline_picker() {
        if ( empty( $this->tests_for_page ) ) {
            return;
        }

        $config = array(
            'rest'  => esc_url_raw( rest_url( 'abti/v1/track' ) ),
            'tests' => $this->tests_for_page,
        );

        $config_json = wp_json_encode( $config );
        // <script> içine güvenli bir şekilde JSON gömmek için </ kaçışı.
        $config_json = str_replace( '</', '<\/', $config_json );

        $picker_js = $this->get_picker_js();

        echo "<!--noptimize-->\n";
        echo '<script id="abti-picker" data-no-optimize="1" data-no-minify="1" data-cfasync="false">';
        echo "\n/* ABTI v" . ABTI_VERSION . " inline picker */\n";
        echo 'window.ABTI_CONFIG=' . $config_json . ";\n";
        echo $picker_js;
        echo "</script>\n";
        echo "<!--/noptimize-->\n";
    }

    /**
     * Picker'ın JS gövdesi. Saf string — minify'a gerek yok.
     */
    private function get_picker_js() {
        return <<<'JS'
(function(){
/* --- hide-all style elementini güvenilir şekilde bul --- */
function findHideStyle(){
  var s=document.getElementById('abti-hide-all');
  if(s)return s;
  // ID minify edilmişse: picker script'inden önceki style'ı bul
  var cs=document.currentScript||(function(){var ss=document.getElementsByTagName('script');return ss[ss.length-1];}());
  if(cs){
    var ps=cs.previousElementSibling;
    while(ps){
      if(ps.tagName==='STYLE'&&ps.getAttribute('data-abti')==='1')return ps;
      ps=ps.previousElementSibling;
    }
  }
  return null;
}
try{
var c=window.ABTI_CONFIG;
if(!c||!c.tests||!c.tests.length)return;
function lg(k){try{return localStorage.getItem(k)}catch(e){return null}}
function ls(k,v){try{localStorage.setItem(k,v)}catch(e){}}
function esc(s){
  if(window.CSS&&CSS.escape)return CSS.escape(s);
  s=String(s);
  var o='',i,ch,code;
  for(i=0;i<s.length;i++){
    ch=s.charAt(i);code=ch.charCodeAt(0);
    if(code===0){o+='\\fffd ';}
    else if((code>=1&&code<=31)||code===127||(i===0&&code>=48&&code<=57)||(i===1&&code>=48&&code<=57&&s.charAt(0)==='-')){o+='\\'+code.toString(16)+' ';}
    else if(i===0&&ch==='-'&&s.length===1){o+='\\-';}
    else if(/[A-Za-z0-9_-]/.test(ch)){o+=ch;}
    else{o+='\\'+ch;}
  }
  return o;
}
function sel(v){return (v.selector_type==='class'?'.':'#')+esc(v.selector)}
var hide=[],assign={};
for(var i=0;i<c.tests.length;i++){
  var t=c.tests[i],ck='abti_test_'+t.id,k=lg(ck),chosen=null;
  if(k){
    for(var j=0;j<t.variations.length;j++){
      if(t.variations[j].key===k){chosen=t.variations[j];break;}
    }
  }
  if(!chosen){
    var tot=0;
    for(var n=0;n<t.variations.length;n++)tot+=Number(t.variations[n].percentage)||0;
    if(tot<=0){
      chosen=t.variations[Math.floor(Math.random()*t.variations.length)];
    }else{
      var r=Math.random()*tot,ac=0;
      for(var m=0;m<t.variations.length;m++){
        ac+=Number(t.variations[m].percentage)||0;
        if(r<ac){chosen=t.variations[m];break;}
      }
      if(!chosen)chosen=t.variations[t.variations.length-1];
    }
    if(chosen&&chosen.key)ls(ck,chosen.key);
  }
  // chosen veya chosen.key yoksa bu test atlanır; PHP index-0'ı zaten görünür bıraktı
  if(!chosen||!chosen.key){continue;}
  assign[t.id]=chosen.key;
  for(var p=0;p<t.variations.length;p++){
    var v=t.variations[p];
    if(v.key!==chosen.key)hide.push(sel(v));
  }
}
window.ABTI_ASSIGN=assign;
var st=findHideStyle();
if(st){
  st.textContent=hide.length?(hide.join(',')+'{display:none !important;}'):'';
}else{
  // Style bulunamadı — elementler zaten görünürdür, sadece assign'ı set et
}
window.ABTI_PICKER_DONE=true;
}catch(e){
  // Picker beklenmedik hata aldı — iki element aynı anda görünmesin.
  // PHP zaten index-0 varyasyonunu görünür bıraktı, style değiştirilmez.
  window.ABTI_PICKER_DONE=false;
}
})();

JS;
    }

    /**
     * Tracking script'i footer'da, defer ile yükle.
     */
    public function enqueue_scripts() {
        if ( empty( $this->tests_for_page ) ) {
            return;
        }

        $handle = 'abti-frontend';
        wp_register_script(
            $handle,
            ABTI_URL . 'public/js/abti-frontend.js',
            array(),
            ABTI_VERSION,
            true // footer.
        );
        wp_enqueue_script( $handle );

        add_filter( 'script_loader_tag', array( $this, 'filter_script_tag' ), 10, 3 );
    }

    public function filter_script_tag( $tag, $handle, $src ) {
        if ( $handle !== 'abti-frontend' ) {
            return $tag;
        }
        // defer + optimizasyon eklentilerine dokunma sinyali.
        return str_replace(
            '<script ',
            '<script defer data-no-optimize="1" data-no-minify="1" data-cfasync="false" ',
            $tag
        );
    }

    /**
     * Varyasyon kaydından CSS selector üretir.
     */
    private function build_selector( $variation ) {
        $name = isset( $variation['selector'] ) ? trim( $variation['selector'] ) : '';
        if ( $name === '' ) {
            return '';
        }
        $type = isset( $variation['selector_type'] ) ? $variation['selector_type'] : 'id';
        $prefix = ( $type === 'class' ) ? '.' : '#';
        return $prefix . $this->escape_css_identifier( $name );
    }

    /**
     * Minimal CSS.escape equivalent for the sanitized ID/class names we allow.
     */
    private function escape_css_identifier( $value ) {
        $value  = (string) $value;
        $length = strlen( $value );
        $out    = '';

        for ( $i = 0; $i < $length; $i++ ) {
            $char = $value[ $i ];
            $ord  = ord( $char );

            $is_digit   = ( $ord >= 48 && $ord <= 57 );
            $is_upper   = ( $ord >= 65 && $ord <= 90 );
            $is_lower   = ( $ord >= 97 && $ord <= 122 );
            $is_control = ( $ord >= 1 && $ord <= 31 ) || $ord === 127;
            $is_safe    = $is_digit || $is_upper || $is_lower || $char === '_' || $char === '-';

            if ( $ord === 0 ) {
                $out .= '\\fffd ';
            } elseif ( $is_control ) {
                $out .= '\\' . dechex( $ord ) . ' ';
            } elseif ( $i === 0 && $is_digit ) {
                $out .= '\\' . dechex( $ord ) . ' ';
            } elseif ( $i === 1 && $is_digit && $value[0] === '-' ) {
                $out .= '\\' . dechex( $ord ) . ' ';
            } elseif ( $i === 0 && $char === '-' && $length === 1 ) {
                $out .= '\\-';
            } elseif ( $is_safe ) {
                $out .= $char;
            } else {
                $out .= '\\' . $char;
            }
        }

        return $out;
    }

    /* ---------- Filter callbacks ---------- */

    private function js_exclusion_patterns() {
        return array(
            'ab-test-int/public/js/abti-frontend.js',
            '/ab-test-int/public/js/abti-frontend.js',
            'abti-frontend.js',
            'abti-frontend',
            'ABTI_CONFIG',
            'ABTI_ASSIGN',
            'abti-picker',
            'ABTI v',
            'window.ABTI_CONFIG',
            'window.ABTI_ASSIGN',
        );
    }

    private function css_exclusion_patterns() {
        return array(
            'abti-hide-all',
            'ABTI',
            'data-no-optimize',
        );
    }

    public function rocket_exclude_js( $excluded ) {
        foreach ( $this->js_exclusion_patterns() as $pattern ) {
            $excluded[] = $pattern;
        }
        return $excluded;
    }

    public function rocket_excluded_inline( $excluded ) {
        foreach ( $this->js_exclusion_patterns() as $pattern ) {
            $excluded[] = $pattern;
        }
        return $excluded;
    }

    public function rocket_delay_js_exclusions( $excluded ) {
        foreach ( $this->js_exclusion_patterns() as $pattern ) {
            $excluded[] = $pattern;
        }
        return $excluded;
    }

    public function rocket_defer_inline_exclusions( $excluded ) {
        if ( ! is_array( $excluded ) ) {
            $excluded = array();
        }
        foreach ( $this->js_exclusion_patterns() as $pattern ) {
            $excluded[] = $pattern;
        }
        return $excluded;
    }

    public function rocket_exclude_css( $excluded ) {
        foreach ( $this->css_exclusion_patterns() as $pattern ) {
            $excluded[] = $pattern;
        }
        return $excluded;
    }

    public function rocket_rucss_inline_content_exclusions( $excluded ) {
        if ( ! is_array( $excluded ) ) {
            $excluded = array();
        }
        foreach ( $this->css_exclusion_patterns() as $pattern ) {
            $excluded[] = $pattern;
        }
        return $excluded;
    }

    public function autoptimize_js_exclude( $excluded ) {
        $list = array_filter( array_map( 'trim', explode( ',', (string) $excluded ) ) );
        foreach ( $this->js_exclusion_patterns() as $pattern ) {
            $list[] = $pattern;
        }
        return implode( ', ', array_unique( $list ) );
    }

    public function autoptimize_css_exclude( $excluded ) {
        $list = array_filter( array_map( 'trim', explode( ',', (string) $excluded ) ) );
        foreach ( $this->css_exclusion_patterns() as $pattern ) {
            $list[] = $pattern;
        }
        return implode( ', ', $list );
    }

    public function wpfc_exclude_js( $excluded ) {
        if ( ! is_array( $excluded ) ) {
            $excluded = array();
        }
        foreach ( $this->js_exclusion_patterns() as $pattern ) {
            $excluded[] = $pattern;
        }
        return $excluded;
    }

    public function litespeed_exclude( $excluded ) {
        if ( ! is_array( $excluded ) ) {
            $excluded = array();
        }
        foreach ( $this->js_exclusion_patterns() as $pattern ) {
            $excluded[] = $pattern;
        }
        return $excluded;
    }

    public function litespeed_css_exclude( $excluded ) {
        if ( ! is_array( $excluded ) ) {
            $excluded = array();
        }
        foreach ( $this->css_exclusion_patterns() as $pattern ) {
            $excluded[] = $pattern;
        }
        return $excluded;
    }

    public function sgo_exclude( $excluded ) {
        if ( ! is_array( $excluded ) ) {
            $excluded = array();
        }
        foreach ( $this->js_exclusion_patterns() as $pattern ) {
            $excluded[] = $pattern;
        }
        return $excluded;
    }

    public function sgo_css_exclude( $excluded ) {
        if ( ! is_array( $excluded ) ) {
            $excluded = array();
        }
        foreach ( $this->css_exclusion_patterns() as $pattern ) {
            $excluded[] = $pattern;
        }
        return $excluded;
    }
}
