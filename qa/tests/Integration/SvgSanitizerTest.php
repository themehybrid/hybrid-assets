<?php

/*
|--------------------------------------------------------------------------
| Vendored-sanitizer conformance tests
|--------------------------------------------------------------------------
|
| The dataset below is ported verbatim from WordPress core's own
| `data_sanitize_inline_svg()` (tests/phpunit/tests/icons/wpIconsRegistry.php,
| Trac #64651 / PR #12197, commit c129d8c). That is the point: these are not
| our expectations, they are core's. If our vendored copy ever diverges from upstream behaviour
| — because we edited it, or because a core change wasn't pulled through —
| these go red with a concrete diff.
|
| Do not "fix" a failure here by editing an expectation. Re-copy it from core,
| or fix the vendored sanitizer. Hand-tuning these to green defeats the entire
| purpose of the file.
|
| Note we call the sanitizer directly. Core's own test needs ReflectionMethod
| because `sanitize_inline_svg()` is private on WP_Icons_Registry; vendoring
| it behind a public static entry point removes that indirection.
|
*/

use Hybrid\Assets\Support\SvgSanitizer;

it( 'matches WordPress core sanitizer expectations', function ( string $input, string $expected ) {
    expect( SvgSanitizer::sanitize( $input ) )->toBe( $expected );
} )->with( function (): array {
    $xlink = ' xmlns:xlink="http://www.w3.org/1999/xlink"';

    return array(
        // Root selection: exactly one SVG element in the SVG namespace.
        'rejects multiple top-level svg elements'      => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><path d="first"/></svg><svg xmlns="http://www.w3.org/2000/svg"><path d="second"/></svg>',
            'expected' => '',
        ),
        'allows nested svg'                            => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg></svg>',
        ),
        'rejects svg in a foreign namespace'           => array(
            'input'    => '<math><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg></math>',
            'expected' => '',
        ),
        'allows a self-closing svg root'               => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg" />',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg" />',
        ),
        'rejects a sibling after a self-closing root'  => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg" /><svg xmlns="http://www.w3.org/2000/svg"><path d="second" /></svg>',
            'expected' => '',
        ),
        'ignores markup after a self-closing root'     => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg" /><path d="orphan" />',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg" />',
        ),

        // Content surrounding the SVG root is ignored.
        'ignores content preceding the svg'            => array(
            'input'    => '<p>before</p><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
        ),
        'ignores content following the svg'            => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg><p>after</p>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
        ),
        'ignores an xml declaration before the svg'    => array(
            'input'    => '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
        ),
        'ignores a comment before the svg'             => array(
            'input'    => '<!-- Generator: some editor --><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
        ),
        'ignores whitespace before the svg'            => array(
            'input'    => "  \n\t<svg xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M0 0\" /></svg>",
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
        ),

        // Non-SVG or unparseable input returns an empty string.
        'returns empty for empty string'               => array(
            'input'    => '',
            'expected' => '',
        ),
        'returns empty for whitespace only'            => array(
            'input'    => "   \n\t  ",
            'expected' => '',
        ),
        'returns empty for plain text'                 => array(
            'input'    => 'plain text without svg',
            'expected' => '',
        ),
        'returns empty for html without an svg'        => array(
            'input'    => '<div>not svg</div><p>content</p>',
            'expected' => '',
        ),
        'returns empty for incomplete markup'          => array(
            'input'    => '<svg><path d="M0 0"',
            'expected' => '',
        ),
        'returns empty for unsupported markup'         => array(
            'input'    => '<svg><foreignObject><table>TEXT NOT SUPPORTED HERE!',
            'expected' => '',
        ),

        // Disallowed or dangerous content is stripped (wp_kses).
        'strips html-like tags inside svg'             => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><p>paragraph content</p><path d="M0 0h24v24H0z" /><div>div content</div></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
        ),
        'strips foreignObject but keeps text content'  => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><p>paragraph content</p><script>alert(1)</script></foreignObject><path d="M0 0h24v24H0z" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg">paragraph contentalert(1)<path d="M0 0h24v24H0z" /></svg>',
        ),
        'strips script tags'                           => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0h24v24H0z" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg">alert(1)<path d="M0 0h24v24H0z" /></svg>',
        ),
        'strips event handler attributes'              => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg" onclick="alert(1)"><path d="M0 0h24v24H0z" onload="evil()" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24v24H0z" /></svg>',
        ),
        'strips javascript protocol in href'           => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><use href="javascript:alert(1)" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><use href="alert(1)" /></svg>',
        ),
        'strips data protocol in href'                 => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><use href="data:text/html,<script>alert(1)</script>" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><use href="text/html,&lt;script&gt;alert(1)&lt;/script&gt;" /></svg>',
        ),
        'strips javascript protocol in xlink:href'     => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="javascript:alert(1)"><path d="M0 0" /></a></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"' . $xlink . '><a xlink:href="alert(1)"><path d="M0 0" /></a></svg>',
        ),
        'strips animate and set elements'              => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><a href="#icon"><animate attributeName="href" to="javascript:alert(1)" dur="1s" /><set attributeName="href" to="javascript:alert(1)" begin="1s" /><path d="M0 0" /></a></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><a href="#icon"><path d="M0 0" /></a></svg>',
        ),
        'strips disallowed tags'                       => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/><iframe src="evil"></iframe><object data="x" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" /></svg>',
        ),

        // Allowed SVG elements and attributes are preserved.
        'preserves the root svg element'               => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24" preserveAspectRatio="xMidYMid meet" width="24" height="24" class="icon" aria-hidden="true"><path d="M0 0" fill="currentColor" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"' . $xlink . ' viewbox="0 0 24 24" preserveaspectratio="xMidYMid meet" width="24" height="24" class="icon" aria-hidden="true"><path d="M0 0" fill="currentColor" /></svg>',
        ),
        'preserves xmlns:xlink attribute'              => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><path d="M0 0h24v24H0z" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"' . $xlink . '><path d="M0 0h24v24H0z" /></svg>',
        ),
        'preserves fill and clip rules on svg'         => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"><path d="M0 0" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd"><path d="M0 0" /></svg>',
        ),
        'preserves stroke attributes and style on svg' => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg" style="fill: none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" vector-effect="non-scaling-stroke"><path d="M0 0" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg" style="fill: none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" vector-effect="non-scaling-stroke"><path d="M0 0" /></svg>',
        ),
        'preserves clip rule, opacity and stroke on path' => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" fill-rule="evenodd" clip-rule="evenodd" opacity="0.4" style="fill: none" stroke="currentColor" vector-effect="non-scaling-stroke" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" fill-rule="evenodd" clip-rule="evenodd" opacity="0.4" style="fill: none" stroke="currentColor" vector-effect="non-scaling-stroke" /></svg>',
        ),
        'preserves clip rule and stroke on polygon'    => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><polygon points="0,0 1,1" clip-rule="evenodd" stroke="currentColor" vector-effect="non-scaling-stroke" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><polygon points="0,0 1,1" clip-rule="evenodd" stroke="currentColor" vector-effect="non-scaling-stroke" /></svg>',
        ),
        'preserves presentation attributes on rect'    => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><rect x="4" y="5" width="16" height="14" rx="2" ry="2" fill="currentColor" stroke="currentColor" transform="rotate(45)" vector-effect="non-scaling-stroke" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><rect x="4" y="5" width="16" height="14" rx="2" ry="2" fill="currentColor" stroke="currentColor" transform="rotate(45)" vector-effect="non-scaling-stroke" /></svg>',
        ),
        'preserves presentation attributes on circle'  => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="3" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" transform="rotate(45)" vector-effect="non-scaling-stroke" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="3" fill="currentColor" fill-rule="evenodd" clip-rule="evenodd" transform="rotate(45)" vector-effect="non-scaling-stroke" /></svg>',
        ),
        'preserves basic shape elements'               => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24v24H0z" /><circle cx="12" cy="12" r="10" /><ellipse cx="12" cy="12" rx="10" ry="8" /><line x1="0" y1="0" x2="24" y2="24" /><polygon points="0,0 24,0 12,24" /><polyline points="0,0 12,12 24,0" /><rect x="2" y="2" width="20" height="20" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h24v24H0z" /><circle cx="12" cy="12" r="10" /><ellipse cx="12" cy="12" rx="10" ry="8" /><line x1="0" y1="0" x2="24" y2="24" /><polygon points="0,0 24,0 12,24" /><polyline points="0,0 12,12 24,0" /><rect x="2" y="2" width="20" height="20" /></svg>',
        ),
        'preserves grouping and structural elements'   => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><defs><symbol id="icon" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" /></symbol><clipPath id="clip"><circle cx="12" cy="12" r="10" /></clipPath><mask id="m"><rect fill="white" width="24" height="24" /></mask></defs><g><use href="#icon" /><use href="https://example.com/icon.svg#symbol" /><use href="#symbol" /></g></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><defs><symbol id="icon" viewbox="0 0 24 24"><path d="M0 0h24v24H0z" /></symbol><clipPath id="clip"><circle cx="12" cy="12" r="10" /></clipPath><mask id="m"><rect fill="white" width="24" height="24" /></mask></defs><g><use href="#icon" /><use href="https://example.com/icon.svg#symbol" /><use href="#symbol" /></g></svg>',
        ),
        'preserves switch element'                     => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><switch><path d="M0 0h24v24H0z" /></switch></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><switch><path d="M0 0h24v24H0z" /></switch></svg>',
        ),
        'preserves view element'                       => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><view id="v" viewBox="0 0 24 24" /><path d="M0 0h24v24H0z" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><view id="v" viewbox="0 0 24 24" /><path d="M0 0h24v24H0z" /></svg>',
        ),
        'preserves linking element'                    => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><a href="https://example.com"><path d="M0 0h24v24H0z" /></a></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><a href="https://example.com"><path d="M0 0h24v24H0z" /></a></svg>',
        ),
        'preserves gradient elements'                  => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><linearGradient id="lin"><stop offset="0%" stop-color="red" /><stop offset="100%" stop-color="blue" /></linearGradient><radialGradient id="rad"><stop offset="0%" stop-color="red" /><stop offset="100%" stop-color="blue" /></radialGradient><rect fill="url(#lin)" width="24" height="24" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><linearGradient id="lin"><stop offset="0%" stop-color="red" /><stop offset="100%" stop-color="blue" /></linearGradient><radialGradient id="rad"><stop offset="0%" stop-color="red" /><stop offset="100%" stop-color="blue" /></radialGradient><rect fill="url(#lin)" width="24" height="24" /></svg>',
        ),
        'preserves pattern element'                    => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><pattern id="pat" width="4" height="4"><rect width="4" height="4" fill="currentColor" /></pattern><rect fill="url(#pat)" width="24" height="24" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><pattern id="pat" width="4" height="4"><rect width="4" height="4" fill="currentColor" /></pattern><rect fill="url(#pat)" width="24" height="24" /></svg>',
        ),
        'preserves filter elements'                    => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><filter id="blur"><feGaussianBlur in="SourceGraphic" stdDeviation="1" /></filter><rect filter="url(#blur)" width="24" height="24" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><filter id="blur"><feGaussianBlur in="SourceGraphic" stddeviation="1" /></filter><rect filter="url(#blur)" width="24" height="24" /></svg>',
        ),
        'preserves text elements'                      => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><path id="p" d="M0,20 Q12,0 24,20" /><text x="12" y="16" text-anchor="middle">A<tspan font-weight="bold">B</tspan></text><text><textPath href="#p">path</textPath></text></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><path id="p" d="M0,20 Q12,0 24,20" /><text x="12" y="16" text-anchor="middle">A<tspan font-weight="bold">B</tspan></text><text><textPath href="#p">path</textPath></text></svg>',
        ),
        'preserves descriptive elements'               => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><title>Icon title</title><desc>Description</desc><metadata></metadata><path d="M0 0h24v24H0z" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><title>Icon title</title><desc>Description</desc><metadata></metadata><path d="M0 0h24v24H0z" /></svg>',
        ),
        'preserves image element'                      => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.com/icon.png" width="24" height="24" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://example.com/icon.png" width="24" height="24" /></svg>',
        ),
        'preserves marker element'                     => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><marker id="arrow" refX="10" refY="5"><path d="M0,0 L10,5 L0,10" /></marker><path d="M0,12 L24,12" marker-start="url(#arrow)" /></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><marker id="arrow" refx="10" refy="5"><path d="M0,0 L10,5 L0,10" /></marker><path d="M0,12 L24,12" marker-start="url(#arrow)" /></svg>',
        ),
        'preserves animation elements'                 => array(
            'input'    => '<svg xmlns="http://www.w3.org/2000/svg"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="2s" /><path d="M0,0 L10,10"><animateMotion path="M0,0 L24,24" dur="1s" /></path></svg>',
            'expected' => '<svg xmlns="http://www.w3.org/2000/svg"><animateTransform attributename="transform" type="rotate" from="0 12 12" to="360 12 12" dur="2s" /><path d="M0,0 L10,10"><animateMotion path="M0,0 L24,24" dur="1s" /></path></svg>',
        ),
    );
} );
