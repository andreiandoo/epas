<?php
/**
 * Ok, glad you are here
 * first we get a config instance, and set the settings
 * $config = HTMLPurifier_Config::createDefault();
 * $config->set('Core.Encoding', $this->config->get('purifier.encoding'));
 * $config->set('Cache.SerializerPath', $this->config->get('purifier.cachePath'));
 * if ( ! $this->config->get('purifier.finalize')) {
 *     $config->autoFinalize = false;
 * }
 * $config->loadArray($this->getConfig());
 *
 * You must NOT delete the default settings
 * anything in settings should be compacted with params that needed to instance HTMLPurifier_Config.
 *
 * @link http://htmlpurifier.org/live/configdoc/plain.html
 */

return [
    'encoding'           => 'UTF-8',
    'finalize'           => true,
    'ignoreNonStrings'   => false,
    'cachePath'          => storage_path('app/purifier'),
    'cacheFileMode'      => 0755,
    'settings'      => [
        'default' => [
            'HTML.Doctype'             => 'HTML 4.01 Transitional',
            'HTML.Allowed'             => 'div,b,strong,i,em,u,a[href|title],ul,ol,li,p[style],br,span[style],img[width|height|alt|src]',
            'CSS.AllowedProperties'    => 'font,font-size,font-weight,font-style,font-family,text-decoration,padding-left,color,background-color,text-align',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.RemoveEmpty'   => true,
        ],
        'test'    => [
            'Attr.EnableID' => 'true',
        ],
        "youtube" => [
            "HTML.SafeIframe"      => 'true',
            "URI.SafeIframeRegexp" => "%^(http://|https://|//)(www.youtube.com/embed/|player.vimeo.com/video/)%",
        ],
        // Custom profile for the marketplace-scoped "SystemUpdate" body
        // (Noutăți / changelog module). Same guarantees as
        // `thank_you_message` (YouTube+Vimeo iframe lock, safe target
        // links, no script/on*/data:URI) but the whitelist is wider:
        // structural / long-form-article tags (h1-h6, blockquote,
        // pre/code, hr, figure/figcaption) are allowed because operators
        // write proper announcement posts here, not tiny messages.
        "system_update" => [
            "HTML.Doctype"             => 'HTML 4.01 Transitional',
            "HTML.Allowed"             => 'p[style],br,b,strong,i,em,u,s,'
                . 'h1,h2,h3,h4,h5,h6,'
                . 'ul,ol,li,blockquote,pre,code,hr,'
                . 'a[href|title|target|rel],'
                . 'img[src|alt|width|height|style],'
                . 'iframe[src|width|height|frameborder|allowfullscreen|allow],'
                . 'span[style],div[style],figure,figcaption,'
                . 'table,thead,tbody,tr,th[colspan|rowspan|style],td[colspan|rowspan|style]',
            "CSS.AllowedProperties"    => 'color,background-color,text-align,'
                . 'font-weight,font-style,text-decoration,'
                . 'padding-left,margin-left,'
                . 'width,height,max-width',
            "HTML.SafeIframe"          => 'true',
            "URI.SafeIframeRegexp"     => "%^(http://|https://|//)(www\\.youtube(?:-nocookie)?\\.com/embed/|player\\.vimeo\\.com/video/)%",
            "Attr.AllowedFrameTargets" => '_blank,_self',
            "HTML.TargetBlank"         => true,
            "AutoFormat.AutoParagraph" => false,
            "AutoFormat.RemoveEmpty"   => true,
        ],
        // Custom profile for per-event post-purchase "thank_you_message":
        //   - full text formatting (b/i/u/s, headings, lists, quotes)
        //   - safe links (target=_blank is normalized to include noopener)
        //   - inline images (any http/https src, no data: URIs → keeps the
        //     JSON payload small; DomPDF/emails render the URL directly)
        //   - safe iframes for YouTube + Vimeo ONLY (regex-locked)
        //   - basic inline color / alignment CSS
        // Everything not on this whitelist is silently stripped, so a
        // pasted <script>, on* event, or <object> tag becomes plain text.
        "thank_you_message" => [
            "HTML.Doctype"             => 'HTML 4.01 Transitional',
            "HTML.Allowed"             => 'p[style],br,b,strong,i,em,u,s,'
                . 'h1,h2,h3,h4,h5,h6,'
                . 'ul,ol,li,blockquote,pre,code,hr,'
                . 'a[href|title|target|rel],'
                . 'img[src|alt|width|height|style],'
                . 'iframe[src|width|height|frameborder|allowfullscreen|allow],'
                . 'span[style],div[style],figure,figcaption',
            "CSS.AllowedProperties"    => 'color,background-color,text-align,'
                . 'font-weight,font-style,text-decoration,'
                . 'padding-left,margin-left,'
                . 'width,height,max-width',
            "HTML.SafeIframe"          => 'true',
            // Only YouTube + Vimeo embed URLs pass through. Anything else
            // gets the iframe dropped entirely.
            "URI.SafeIframeRegexp"     => "%^(http://|https://|//)(www\\.youtube(?:-nocookie)?\\.com/embed/|player\\.vimeo\\.com/video/)%",
            "Attr.AllowedFrameTargets" => '_blank,_self',
            // Normalizes target=_blank links to include rel=noopener — mid
            // 2020s security baseline.
            "HTML.TargetBlank"         => true,
            "AutoFormat.AutoParagraph" => false,
            "AutoFormat.RemoveEmpty"   => true,
        ],
        'custom_definition' => [
            'id'  => 'html5-definitions',
            'rev' => 1,
            'debug' => false,
            'elements' => [
                // http://developers.whatwg.org/sections.html
                ['section', 'Block', 'Flow', 'Common'],
                ['nav',     'Block', 'Flow', 'Common'],
                ['article', 'Block', 'Flow', 'Common'],
                ['aside',   'Block', 'Flow', 'Common'],
                ['header',  'Block', 'Flow', 'Common'],
                ['footer',  'Block', 'Flow', 'Common'],
				
				// Content model actually excludes several tags, not modelled here
                ['address', 'Block', 'Flow', 'Common'],
                ['hgroup', 'Block', 'Required: h1 | h2 | h3 | h4 | h5 | h6', 'Common'],
				
				// http://developers.whatwg.org/grouping-content.html
                ['figure', 'Block', 'Optional: (figcaption, Flow) | (Flow, figcaption) | Flow', 'Common'],
                ['figcaption', 'Inline', 'Flow', 'Common'],
				
				// http://developers.whatwg.org/the-video-element.html#the-video-element
                ['video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', [
                    'src' => 'URI',
					'type' => 'Text',
					'width' => 'Length',
					'height' => 'Length',
					'poster' => 'URI',
					'preload' => 'Enum#auto,metadata,none',
					'controls' => 'Bool',
                ]],
                ['source', 'Block', 'Flow', 'Common', [
					'src' => 'URI',
					'type' => 'Text',
                ]],

				// http://developers.whatwg.org/text-level-semantics.html
                ['s',    'Inline', 'Inline', 'Common'],
                ['var',  'Inline', 'Inline', 'Common'],
                ['sub',  'Inline', 'Inline', 'Common'],
                ['sup',  'Inline', 'Inline', 'Common'],
                ['mark', 'Inline', 'Inline', 'Common'],
                ['wbr',  'Inline', 'Empty', 'Core'],
				
				// http://developers.whatwg.org/edits.html
                ['ins', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
                ['del', 'Block', 'Flow', 'Common', ['cite' => 'URI', 'datetime' => 'CDATA']],
            ],
            'attributes' => [
                ['iframe', 'allowfullscreen', 'Bool'],
                ['table', 'height', 'Text'],
                ['td', 'border', 'Text'],
                ['th', 'border', 'Text'],
                ['tr', 'width', 'Text'],
                ['tr', 'height', 'Text'],
                ['tr', 'border', 'Text'],
            ],
        ],
        'custom_attributes' => [
            ['a', 'target', 'Enum#_blank,_self,_target,_top'],
        ],
        'custom_elements' => [
            ['u', 'Inline', 'Inline', 'Common'],
        ],
    ],

];
