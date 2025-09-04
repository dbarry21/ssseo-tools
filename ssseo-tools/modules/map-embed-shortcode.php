<?php
/**
 * Module: Google Maps Embed API Shortcode
 * Shortcode: [ssseo_map_embed q="Pasco County, FL" width="100%" height="400" mode="place"]
 * - If q="" omitted, uses either a provided field (ACF/meta) via field="…" or falls back to geo/city_state.
 * - mode: place | directions | search | streetview
 * - For directions: provide origin / destination (addresses or "lat,lng")
 * - NEW: ratio="16x9" (default) | 4x3 | 1x1 | 21x9 | {AxB} | none
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function ssseo_map_embed_api_key() {
    return trim( get_option( 'ssseo_google_static_maps_api_key', '' ) );
}

function ssseo_map_embed_get_from_field( $post_id, $field_name ) {
    if ( ! $post_id || ! $field_name ) return '';
    $val = '';

    if ( function_exists('get_field') ) {
        $acf_val = get_field( $field_name, $post_id );
        if ( is_array($acf_val) ) {
            if ( isset($acf_val['address']) && is_string($acf_val['address']) ) {
                $val = $acf_val['address'];
            } elseif ( isset($acf_val['latitude'], $acf_val['longitude']) ) {
                $val = "{$acf_val['latitude']},{$acf_val['longitude']}";
            }
        } elseif ( is_string($acf_val) ) {
            $val = $acf_val;
        }
    }

    if ( $val === '' ) {
        $meta = get_post_meta( $post_id, $field_name, true );
        if ( is_array($meta) ) {
            if ( isset($meta['latitude'], $meta['longitude']) ) {
                $val = "{$meta['latitude']},{$meta['longitude']}";
            }
        } elseif ( is_string($meta) ) {
            $val = $meta;
        }
    }

    return sanitize_text_field( (string) $val );
}

function ssseo_map_embed_guess_q( $post_id ) {
    if ( ! $post_id ) return '';
    if ( function_exists('get_field') ) {
        $geo = get_field( 'geo_coordinates', $post_id );
        if ( is_array($geo) && !empty($geo['latitude']) && !empty($geo['longitude']) ) {
            return sanitize_text_field( $geo['latitude'] . ',' . $geo['longitude'] );
        }
        $cs = get_field( 'city_state', $post_id );
        if ( $cs ) return sanitize_text_field( $cs );
    }
    $meta = get_post_meta( $post_id, 'geo_coordinates', true );
    if ( is_array($meta) && !empty($meta['latitude']) && !empty($meta['longitude']) ) {
        return sanitize_text_field( $meta['latitude'] . ',' . $meta['longitude'] );
    }
    $cs = get_post_meta( $post_id, 'city_state', true );
    return $cs ? sanitize_text_field( $cs ) : '';
}

/** Parse ratio like "16x9" / "3:2" / "21/9" → [w,h] floats or [] */
function ssseo_map_embed_parse_ratio($s) {
    if (!is_string($s) || $s === '') return [];
    if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*[:x\/]\s*(\d+(?:\.\d+)?)\s*$/i', $s, $m)) {
        $w = (float)$m[1]; $h = (float)$m[2];
        if ($w > 0 && $h > 0) return [$w, $h];
    }
    return [];
}

add_shortcode('ssseo_map_embed', function( $atts ) {
    $a = shortcode_atts([
        // Source
        'q'        => '',
        'field'    => '',
        // Sizing / CSS
        'width'    => '100%',     // wrapper width (when ratio used) or iframe width (ratio="none")
        'height'   => '400',      // used ONLY when ratio="none"
        'class'    => '',         // appended to default "ssseo-map" (applied to iframe)
        'ratio'    => '16x9',     // NEW: 16x9 (default) | 4x3 | 1x1 | 21x9 | custom AxB | "none"
        // Mode
        'mode'     => 'place',    // place|directions|search|streetview
        // Directions
        'origin'      => '',
        'destination' => '',
        'mode_drive'  => 'driving', // driving|walking|bicycling|transit
        // Street View
        'location' => '',
        'heading'  => '',
        'pitch'    => '',
        'fov'      => '',
    ], $atts, 'ssseo_map_embed' );

    $key = ssseo_map_embed_api_key();
    if ( ! $key ) return '<em>Missing Google Maps API key.</em>';

    $post_id = get_the_ID();
    if ( $a['q'] === '' ) {
        if ( $a['field'] !== '' ) $a['q'] = ssseo_map_embed_get_from_field( $post_id, $a['field'] );
        if ( $a['q'] === '' )      $a['q'] = ssseo_map_embed_guess_q( $post_id );
    }

    $base = 'https://www.google.com/maps/embed/v1/';
    $src  = '';
    $mode = strtolower( $a['mode'] );

    switch ( $mode ) {
        case 'directions':
            $params = [
                'key'        => $key,
                'destination'=> $a['destination'] ?: $a['q'],
            ];
            if ( $a['origin'] )     $params['origin'] = $a['origin'];
            if ( $a['mode_drive'] ) $params['mode']   = $a['mode_drive'];
            $src = add_query_arg( $params, $base . 'directions' );
            break;

        case 'search':
            $src = add_query_arg([
                'key' => $key,
                'q'   => $a['q'] ?: 'coffee',
            ], $base . 'search' );
            break;

        case 'streetview':
            $src = add_query_arg(array_filter([
                'key'      => $key,
                'location' => $a['location'] ?: $a['q'],
                'heading'  => $a['heading'],
                'pitch'    => $a['pitch'],
                'fov'      => $a['fov'],
            ]), $base . 'streetview');
            break;

        case 'place':
        default:
            $src = add_query_arg([
                'key' => $key,
                'q'   => $a['q'] ?: 'Pasco County, FL',
            ], $base . 'place' );
            break;
    }

    // Classes: always include "ssseo-map" on the iframe
    $iframe_classes = trim( 'ssseo-map ' . (string)$a['class'] );
    $iframe_cls_attr = $iframe_classes ? ' class="'.esc_attr($iframe_classes).'"' : '';

    // RATIO HANDLING (default 16x9)
    $ratio = strtolower( trim( (string)$a['ratio'] ) );
    $width_css = esc_attr( $a['width'] ); // applies to wrapper when ratio is used

    if ( $ratio !== 'none' ) {
        // Bootstrap built-ins:
        $builtin = ['16x9','4x3','1x1','21x9'];
        $wrap_class = 'ratio';
        $wrap_style = '';

        if ( in_array($ratio, $builtin, true) ) {
            $wrap_class .= ' ratio-' . $ratio;
        } else {
            // Custom ratio via --bs-aspect-ratio
            $wh = ssseo_map_embed_parse_ratio($ratio);
            if ( $wh ) {
                $pct = ($wh[1] / $wh[0]) * 100.0; // height/width * 100%
                $wrap_style .= '--bs-aspect-ratio:' . esc_attr( rtrim(rtrim(number_format($pct, 6, '.', ''), '0'), '.') ) . '%;';
            } else {
                // fallback to default 16x9 if malformed
                $wrap_class .= ' ratio-16x9';
            }
        }
        // Optional width on wrapper (e.g., 600px or 75%)
        if ( $width_css && $width_css !== '100%' ) {
            $wrap_style .= 'width:' . $width_css . ';';
        }

        $wrap_style_attr = $wrap_style ? ' style="'.esc_attr($wrap_style).'"' : '';
        // child fills wrapper automatically (Bootstrap .ratio > * gets width/height:100%)
        return '<div class="'.esc_attr($wrap_class).'"'.$wrap_style_attr.'>'.
                 '<iframe'.$iframe_cls_attr.' loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" src="'.esc_url($src).'"></iframe>'.
               '</div>';
    }

    // No ratio: use explicit width/height on iframe
    $w = esc_attr( $a['width'] );
    $h = esc_attr( is_numeric($a['height']) ? $a['height'].'px' : $a['height'] );

    return '<iframe'.$iframe_cls_attr.' width="'.$w.'" height="'.$h.'" loading="lazy" allowfullscreen '.
           'referrerpolicy="no-referrer-when-downgrade" src="'.esc_url($src).'"></iframe>';
});
