<?php

namespace Hybrid\Assets\Support;

/**
 * SVG sanitizer, ported from WordPress core's `WP_Icons_Registry`.
 *
 * ---------------------------------------------------------------------
 * VENDORED CODE — NOT OURS. TRACK, DON'T MODIFY.
 * ---------------------------------------------------------------------
 * Source:  WP_Icons_Registry::sanitize_inline_svg() + get_allowed_attribute_list()
 * Ticket:  https://core.trac.wordpress.org/ticket/64847
 * PR:      https://github.com/WordPress/wordpress-develop/pull/12197
 * Ported:  2026-10-22, from PR commit c129d8c ("Harden the SVG sanitizer
 *          against href overrides and self-closing roots")
 * License: GPL-2.0-or-later
 */
final class SvgSanitizer {
    /**
     * Sanitizes an SVG embedded in an HTML fragment.
     *
     * Returns an empty string (rather than throwing) whenever the input has
     * no valid, safe SVG to offer — the fail-closed shape of the upstream
     * method.
     *
     * @param string $htmlContainingSvg HTML fragment containing the SVG to sanitize.
     *
     * @return string The sanitized SVG, or an empty string when no valid
     *                SVG is found.
     */
    public static function sanitize( string $htmlContainingSvg ): string {
        return self::sanitizeInlineSvg( $htmlContainingSvg );
    }

    /**
     * Returns the allowed tag and attribute map used to sanitize SVG markup.
     *
     * @return array<string, array<string, true>> Tag names mapped to their
     *                                            allowed attribute names.
     */
    public static function allowedTags(): array {
        // Core attributes applicable to most elements. `data-*` is a wildcard
        // supported by wp_kses() and matches any data attribute.
        $core_attributes = self::getAllowedAttributeList( 'class', 'data-*', 'id', 'style' );

        /*
         * ARIA and accessibility attributes. wp_kses() does not support an
         * `aria-*` wildcard, so every ARIA state and property is listed
         * explicitly. The list mirrors the WAI-ARIA states and properties.
         *
         * @link https://www.w3.org/TR/wai-aria-1.2/#state_prop_def
         */
        $aria_attributes = self::getAllowedAttributeList(
            'aria-activedescendant',
            'aria-atomic',
            'aria-autocomplete',
            'aria-busy',
            'aria-checked',
            'aria-colcount',
            'aria-colindex',
            'aria-colspan',
            'aria-controls',
            'aria-current',
            'aria-describedby',
            'aria-description',
            'aria-details',
            'aria-disabled',
            'aria-dropeffect',
            'aria-errormessage',
            'aria-expanded',
            'aria-flowto',
            'aria-grabbed',
            'aria-haspopup',
            'aria-hidden',
            'aria-invalid',
            'aria-keyshortcuts',
            'aria-label',
            'aria-labelledby',
            'aria-level',
            'aria-live',
            'aria-modal',
            'aria-multiline',
            'aria-multiselectable',
            'aria-orientation',
            'aria-owns',
            'aria-placeholder',
            'aria-posinset',
            'aria-pressed',
            'aria-readonly',
            'aria-relevant',
            'aria-required',
            'aria-roledescription',
            'aria-rowcount',
            'aria-rowindex',
            'aria-rowspan',
            'aria-selected',
            'aria-setsize',
            'aria-sort',
            'aria-valuemax',
            'aria-valuemin',
            'aria-valuenow',
            'aria-valuetext',
            'focusable',
            'role',
            'tabindex'
        );

        // Presentation attributes for graphics elements (shapes, text, use, image).
        $presentation_attributes = self::getAllowedAttributeList(
            'clip-path',
            'clip-rule',
            'color',
            'color-interpolation',
            'color-rendering',
            'display',
            'fill',
            'fill-opacity',
            'fill-rule',
            'filter',
            'mask',
            'opacity',
            'paint-order',
            'stroke',
            'stroke-dasharray',
            'stroke-dashoffset',
            'stroke-linecap',
            'stroke-linejoin',
            'stroke-miterlimit',
            'stroke-opacity',
            'stroke-width',
            'transform',
            'vector-effect',
            'visibility'
        );

        // Marker attributes (only for shape elements).
        $marker_attributes = self::getAllowedAttributeList( 'marker-end', 'marker-mid', 'marker-start' );

        // Container attributes for grouping elements.
        $container_attributes = self::getAllowedAttributeList(
            'clip-path',
            'display',
            'filter',
            'mask',
            'opacity',
            'transform',
            'visibility'
        );

        /*
         * Allowed tags for wp_kses(). WP_HTML_Processor::normalize() with
         * constraints (similar structure to this array) is proposed to improve
         * HTML/SVG sanitization in the future.
         *
         * @link https://github.com/dmsnell/wordpress-develop/pull/20
         */
        return [
            // Root SVG element.
            'svg'                 => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                self::getAllowedAttributeList(
                    'height',
                    'preserveaspectratio',
                    'viewbox',
                    'width',
                    'x',
                    'xmlns',
                    'xmlns:xlink',
                    'y'
                )
            ),
            // Basic shape elements (with markers).
            'path'                => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                $marker_attributes,
                self::getAllowedAttributeList(
                    'd',
                    'pathlength'
                )
            ),
            'circle'              => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                $marker_attributes,
                self::getAllowedAttributeList(
                    'cx',
                    'cy',
                    'r'
                )
            ),
            'ellipse'             => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                $marker_attributes,
                self::getAllowedAttributeList(
                    'cx',
                    'cy',
                    'rx',
                    'ry'
                )
            ),
            'line'                => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                $marker_attributes,
                self::getAllowedAttributeList(
                    'x1',
                    'x2',
                    'y1',
                    'y2'
                )
            ),
            'polygon'             => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                $marker_attributes,
                self::getAllowedAttributeList(
                    'points'
                )
            ),
            'polyline'            => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                $marker_attributes,
                self::getAllowedAttributeList(
                    'points'
                )
            ),
            'rect'                => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                $marker_attributes,
                self::getAllowedAttributeList(
                    'height',
                    'rx',
                    'ry',
                    'width',
                    'x',
                    'y'
                )
            ),
            // Grouping and structural elements.
            'g'                   => array_merge(
                $core_attributes,
                $aria_attributes,
                $container_attributes
            ),
            'defs'                => $core_attributes,
            'view'                => array_merge(
                $core_attributes,
                self::getAllowedAttributeList(
                    'preserveaspectratio',
                    'viewbox',
                    'viewtarget',
                    'zoomandpan'
                )
            ),
            'symbol'              => array_merge(
                $core_attributes,
                $aria_attributes,
                $container_attributes,
                self::getAllowedAttributeList(
                    'height',
                    'preserveaspectratio',
                    'viewbox',
                    'width',
                    'x',
                    'y'
                )
            ),
            'use'                 => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                self::getAllowedAttributeList(
                    'height',
                    'href',
                    'width',
                    'x',
                    'xlink:href',
                    'y'
                )
            ),
            'switch'              => array_merge(
                $core_attributes,
                $aria_attributes,
                $container_attributes
            ),
            // Linking element.
            'a'                   => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                $container_attributes,
                self::getAllowedAttributeList(
                    'href',
                    'rel',
                    'target',
                    'type',
                    'xlink:href'
                )
            ),
            'clippath'            => array_merge(
                $core_attributes,
                self::getAllowedAttributeList(
                    'clippathunits',
                    'transform'
                )
            ),
            'mask'                => array_merge(
                $core_attributes,
                self::getAllowedAttributeList(
                    'height',
                    'maskcontentunits',
                    'maskunits',
                    'width',
                    'x',
                    'y'
                )
            ),
            // Gradient elements.
            'lineargradient'      => array_merge(
                $core_attributes,
                self::getAllowedAttributeList(
                    'gradienttransform',
                    'gradientunits',
                    'href',
                    'spreadmethod',
                    'x1',
                    'x2',
                    'xlink:href',
                    'y1',
                    'y2'
                )
            ),
            'radialgradient'      => array_merge(
                $core_attributes,
                self::getAllowedAttributeList(
                    'cx',
                    'cy',
                    'fr',
                    'fx',
                    'fy',
                    'gradienttransform',
                    'gradientunits',
                    'href',
                    'r',
                    'spreadmethod',
                    'xlink:href'
                )
            ),
            'stop'                => array_merge(
                $core_attributes,
                self::getAllowedAttributeList(
                    'offset',
                    'stop-color',
                    'stop-opacity'
                )
            ),
            // Pattern element.
            'pattern'             => array_merge(
                $core_attributes,
                self::getAllowedAttributeList(
                    'height',
                    'href',
                    'patterncontentunits',
                    'patterntransform',
                    'patternunits',
                    'preserveaspectratio',
                    'viewbox',
                    'width',
                    'x',
                    'xlink:href',
                    'y'
                )
            ),
            // Filter elements.
            'filter'              => array_merge(
                $core_attributes,
                self::getAllowedAttributeList(
                    'filterunits',
                    'height',
                    'primitiveunits',
                    'width',
                    'x',
                    'y'
                )
            ),
            'feblend'             => self::getAllowedAttributeList(
                'in',
                'in2',
                'mode',
                'result'
            ),
            'fecolormatrix'       => self::getAllowedAttributeList(
                'in',
                'result',
                'type',
                'values'
            ),
            'fecomponenttransfer' => self::getAllowedAttributeList(
                'in',
                'result'
            ),
            'fecomposite'         => self::getAllowedAttributeList(
                'in',
                'in2',
                'k1',
                'k2',
                'k3',
                'k4',
                'operator',
                'result'
            ),
            'feconvolvematrix'    => self::getAllowedAttributeList(
                'bias',
                'divisor',
                'edgemode',
                'in',
                'kernelmatrix',
                'order',
                'preservealpha',
                'result',
                'targetx',
                'targety'
            ),
            'fediffuselighting'   => self::getAllowedAttributeList(
                'diffuseconstant',
                'in',
                'result',
                'surfacescale'
            ),
            'fedisplacementmap'   => self::getAllowedAttributeList(
                'in',
                'in2',
                'result',
                'scale',
                'xchannelselector',
                'ychannelselector'
            ),
            'fedistantlight'      => self::getAllowedAttributeList(
                'azimuth',
                'elevation'
            ),
            'feflood'             => self::getAllowedAttributeList(
                'flood-color',
                'flood-opacity',
                'result'
            ),
            'fegaussianblur'      => self::getAllowedAttributeList(
                'edgemode',
                'in',
                'result',
                'stddeviation'
            ),
            'feimage'             => self::getAllowedAttributeList(
                'href',
                'preserveaspectratio',
                'result',
                'xlink:href'
            ),
            'femerge'             => self::getAllowedAttributeList(
                'result'
            ),
            'femergenode'         => self::getAllowedAttributeList(
                'in'
            ),
            'femorphology'        => self::getAllowedAttributeList(
                'in',
                'operator',
                'radius',
                'result'
            ),
            'feoffset'            => self::getAllowedAttributeList(
                'dx',
                'dy',
                'in',
                'result'
            ),
            'fepointlight'        => self::getAllowedAttributeList(
                'x',
                'y',
                'z'
            ),
            'fespecularlighting'  => self::getAllowedAttributeList(
                'in',
                'result',
                'specularconstant',
                'specularexponent',
                'surfacescale'
            ),
            'fespotlight'         => self::getAllowedAttributeList(
                'limitingconeangle',
                'pointsatx',
                'pointsaty',
                'pointsatz',
                'specularexponent',
                'x',
                'y',
                'z'
            ),
            'fetile'              => self::getAllowedAttributeList(
                'in',
                'result'
            ),
            'feturbulence'        => self::getAllowedAttributeList(
                'basefrequency',
                'numoctaves',
                'result',
                'seed',
                'stitchtiles',
                'type'
            ),
            'fefunca'             => self::getAllowedAttributeList(
                'amplitude',
                'exponent',
                'intercept',
                'offset',
                'slope',
                'tablevalues',
                'type'
            ),
            'fefuncb'             => self::getAllowedAttributeList(
                'amplitude',
                'exponent',
                'intercept',
                'offset',
                'slope',
                'tablevalues',
                'type'
            ),
            'fefuncg'             => self::getAllowedAttributeList(
                'amplitude',
                'exponent',
                'intercept',
                'offset',
                'slope',
                'tablevalues',
                'type'
            ),
            'fefuncr'             => self::getAllowedAttributeList(
                'amplitude',
                'exponent',
                'intercept',
                'offset',
                'slope',
                'tablevalues',
                'type'
            ),
            // Text elements.
            'text'                => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                self::getAllowedAttributeList(
                    'alignment-baseline',
                    'baseline-shift',
                    'dominant-baseline',
                    'dx',
                    'dy',
                    'font-family',
                    'font-size',
                    'font-style',
                    'font-variant',
                    'font-weight',
                    'lengthadjust',
                    'letter-spacing',
                    'rotate',
                    'text-anchor',
                    'text-decoration',
                    'textlength',
                    'word-spacing',
                    'writing-mode',
                    'x',
                    'y'
                )
            ),
            'tspan'               => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                self::getAllowedAttributeList(
                    'dx',
                    'dy',
                    'font-family',
                    'font-size',
                    'font-style',
                    'font-weight',
                    'lengthadjust',
                    'rotate',
                    'text-anchor',
                    'text-decoration',
                    'textlength',
                    'x',
                    'y'
                )
            ),
            'textpath'            => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                self::getAllowedAttributeList(
                    'href',
                    'method',
                    'spacing',
                    'startoffset',
                    'text-anchor',
                    'xlink:href'
                )
            ),
            // Descriptive elements.
            'title'               => [],
            'desc'                => [],
            'metadata'            => [],
            // Image element.
            'image'               => array_merge(
                $core_attributes,
                $aria_attributes,
                $presentation_attributes,
                self::getAllowedAttributeList(
                    'height',
                    'href',
                    'preserveaspectratio',
                    'width',
                    'x',
                    'xlink:href',
                    'y'
                )
            ),
            // Marker element.
            'marker'              => array_merge(
                $core_attributes,
                self::getAllowedAttributeList(
                    'markerheight',
                    'markerunits',
                    'markerwidth',
                    'orient',
                    'preserveaspectratio',
                    'refx',
                    'refy',
                    'viewbox'
                )
            ),
            /*
             * Animation elements. `<animate>` and `<set>` are excluded: their
             * `attributeName` targets any attribute at runtime, letting them
             * replace a value wp_kses() already validated, such as `href`.
             */
            'animatemotion'       => array_merge(
                $core_attributes,
                self::getAllowedAttributeList(
                    'accumulate',
                    'additive',
                    'begin',
                    'calcmode',
                    'dur',
                    'end',
                    'from',
                    'keypoints',
                    'keysplines',
                    'keytimes',
                    'path',
                    'repeatcount',
                    'rotate',
                    'to',
                    'values'
                )
            ),
            'animatetransform'    => array_merge(
                $core_attributes,
                self::getAllowedAttributeList(
                    'accumulate',
                    'additive',
                    'attributename',
                    'begin',
                    'calcmode',
                    'dur',
                    'end',
                    'from',
                    'keysplines',
                    'keytimes',
                    'repeatcount',
                    'to',
                    'type',
                    'values'
                )
            ),
        ];
    }

    /**
     * Sanitizes an SVG embedded in an HTML fragment.
     *
     * The input SVG must have been extracted as HTML from a broader HTML
     * document, NOT as an entire XML document from an external file or JSON
     * value. Parsed as HTML, XML-only constructs (CDATA, `<foreignObject>`
     * integration points) are mis-parsed. WP_HTML_Processor extracts the whole
     * SVG element before wp_kses runs, so inner HTML tags like `<p>` do not
     * terminate the SVG and self-closing tags are handled correctly.
     *
     * @since 7.1.0
     *
     * @param string $html_containing_svg HTML fragment containing the SVG to sanitize.
     *
     * @return string The sanitized SVG, or an empty string when no valid SVG is found.
     */
    private static function sanitizeInlineSvg( $html_containing_svg ) {
        $processor = \WP_HTML_Processor::create_fragment( $html_containing_svg );
        if ( ! $processor ) {
            return '';
        }

        /*
         * Find the first SVG root, ignoring surrounding content. The namespace
         * check rejects a foreign-namespaced `<svg>`, such as in `<math><svg>`.
         */
        if ( ! $processor->next_tag( 'SVG' ) || 'svg' !== $processor->get_namespace() ) {
            return '';
        }

        /*
         * A self-closing `<svg />` has no descendants and no closing tag. Scanning
         * past it would swallow the next sibling and hide it from the check below.
         */
        $svg = $processor->serialize_token();
        if ( $processor->expects_closer() ) {
            $depth = $processor->get_current_depth();
            while ( $processor->next_token() && $processor->get_current_depth() >= $depth ) {
                $svg .= $processor->serialize_token();
            }

            /*
             * An early stop inside an SVG means truncated input, not unsupported
             * markup. Reject it: the parser can synthesize closing tags that were
             * never written, so no valid document remains to trust.
             */
            if (
                null !== $processor->get_last_error()
                || $processor->paused_at_incomplete_token()
            ) {
                return '';
            }
            $svg .= '</svg>';
        }

        /*
         * Reject more than one top-level SVG. Nested SVGs were extracted above,
         * so only sibling roots remain to be found.
         */
        while ( $processor->next_tag( 'SVG' ) ) {
            if ( 'svg' === $processor->get_namespace() ) {
                return '';
            }
        }

        /*
         * `xlink:href` holds a URI but is missing from wp_kses_uri_attributes(),
         * so wp_kses() would not run it through wp_kses_bad_protocol(). Add it
         * for this call only.
         */
        $allow_xlink_href = static function ( $uri_attributes ) {
            $uri_attributes[] = 'xlink:href';
            return $uri_attributes;
        };

        add_filter( 'wp_kses_uri_attributes', $allow_xlink_href );
        $sanitized_svg = wp_kses( $svg, self::allowedTags() );
        remove_filter( 'wp_kses_uri_attributes', $allow_xlink_href );

        return $sanitized_svg;
    }

    /**
     * Builds the allowed attribute list for wp_kses() from attribute names.
     *
     * @since 7.1.0
     *
     * @param string ...$attribute_names Attribute names to allow.
     *
     * @return array Attribute names mapped to true.
     */
    private static function getAllowedAttributeList( ...$attribute_names ) {
        return array_fill_keys( $attribute_names, true );
    }
}
