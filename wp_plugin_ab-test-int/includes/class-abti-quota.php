<?php
/**
 * Hedef yuzdelere gore siradaki varyasyonu secen saf kota hesaplayicisi.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ABTI_Quota {

    /**
     * En buyuk hedef acigina sahip varyasyonu secer.
     * Aciklar esitse toplam atama sayisina gore deterministik rotasyon yapar.
     */
    public static function choose_variation( $variations, $counts ) {
        $candidates   = array();
        $total_weight = 0.0;
        $total_count  = 0;

        foreach ( (array) $variations as $variation ) {
            $key = isset( $variation['key'] ) ? (string) $variation['key'] : '';
            if ( $key === '' ) {
                continue;
            }

            $weight = isset( $variation['percentage'] ) ? max( 0.0, (float) $variation['percentage'] ) : 0.0;
            $count  = isset( $counts[ $key ] ) ? max( 0, (int) $counts[ $key ] ) : 0;

            $candidates[] = array(
                'key'    => $key,
                'weight' => $weight,
                'count'  => $count,
            );
            $total_weight += $weight;
            $total_count  += $count;
        }

        if ( empty( $candidates ) ) {
            return '';
        }

        if ( $total_weight <= 0 ) {
            $total_weight = (float) count( $candidates );
            foreach ( $candidates as $index => $candidate ) {
                $candidates[ $index ]['weight'] = 1.0;
            }
        }

        $next_total  = $total_count + 1;
        $max_deficit = null;
        $tied        = array();

        foreach ( $candidates as $candidate ) {
            $ideal   = $next_total * ( $candidate['weight'] / $total_weight );
            $deficit = $ideal - $candidate['count'];

            if ( $max_deficit === null || $deficit > $max_deficit + 0.0000001 ) {
                $max_deficit = $deficit;
                $tied        = array( $candidate['key'] );
            } elseif ( abs( $deficit - $max_deficit ) <= 0.0000001 ) {
                $tied[] = $candidate['key'];
            }
        }

        $tie_index = $total_count % count( $tied );
        return $tied[ $tie_index ];
    }
}
