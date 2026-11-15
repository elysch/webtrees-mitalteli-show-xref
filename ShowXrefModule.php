<?php

declare(strict_types=1);

namespace Mitalteli\Webtrees\Module\ShowXref;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleSidebarTrait;
use Fisharebest\Webtrees\Module\ModuleSidebarInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Webtrees;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Registry;

/**
 * Display media objects as in webtrees 2.0
 */
class ShowXrefModule extends AbstractModule implements ModuleCustomInterface, ModuleSidebarInterface, ModuleGlobalInterface, ModuleConfigInterface
{
    use ModuleCustomTrait;
    use ModuleSidebarTrait;
    use ModuleConfigTrait;

    public const CUSTOM_AUTHOR = 'elysch';
    public const CUSTOM_VERSION = '4.3.1';
    public const GITHUB_REPO = 'webtrees-mitalteli-show-xref';
    public const AUTHOR_WEBSITE = 'https://github.com/elysch/webtrees-mitalteli-show-xref/';
    public const CUSTOM_SUPPORT_URL = self::AUTHOR_WEBSITE . 'issues';

    /** Sorts the module next to the author's other modules in the module list.
     * The prefix is added in a single place, customTranslations(), to the module title (MODULE_TITLE).
     * Every place that translates it (title(), the preferences page, the flash message...) gets the prefix
     * from there, in every language (also when the language has no translation of it and the English text
     * is used). */
    private const TITLE_PREFIX = "\u{26EE} "; # ⛮

    /** Name of the module. msgid of the translation that customTranslations() prefixes. */
    public const MODULE_TITLE = 'XREF and UID values module.';

    private const REGEX_UID_INTERNAL = '[0-9a-fA-F]{8}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{12}|[0-9a-fA-F]{36}|[0-9a-fA-F]{38}';

    /** Font family name used for the link symbols (declared with @font-face by symbolFontCss()). */
    private const SYMBOL_FONT_FAMILY = 'MitalteliSymbols';

    /**
     * Font files served by the module itself (inside resources/) and the Unicode ranges each one is used for.
     * The browser only downloads a file when the page contains a character of its range that no earlier
     * font in the font stack can draw (see symbolFontCss()), so users do not have to install anything.
     * The ranges must not be wider than what the file really contains (see resources/fonts/NOTICE.txt).
     */
    private const SYMBOL_FONT_FACES = [
        'fonts/mitalteli-symbols.woff' => 'U+2190-21FF, U+25A0-25FF, U+2700-27BF, U+27F0-27FF, U+2900-297F, U+2B00-2BFF, U+1F800-1F8FF',
    ];

    public ModuleService $module_service;

    /**
     *
     * @param ModuleService $module_service
     */
    public function __construct(ModuleService $module_service)
    {
        $this->module_service = $module_service;
    }

    /**
     * Method to evaluate if exists method firstUID is defined in GedcomRecord class
     *
     * @return boolean
     */
    static function firstUidMethodExists(): bool
    {
        return (bool) method_exists(GedcomRecord::class, "firstUid");
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {
        /* I18N: Name of a module. The sorting prefix is injected by customTranslations() */
        return I18N::translate(self::MODULE_TITLE);
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        return I18N::translate('A sidebar to show XREF and UID values.');
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * A URL that will provide the latest stable version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/' . self::CUSTOM_AUTHOR . '/' . self::GITHUB_REPO . '/main/latest-version.txt';
    }

     /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return self::AUTHOR_WEBSITE;
    }

    /**
     * Bootstrap the module
     */
    public function boot(): void
    {
        // Register a namespace for our views.
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }

     /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * Additional translations for module.
     *
     * @param string $language
     *
     * @return string[]
     */
    public function customTranslations(string $language): array
    {
        $translations = [];

        // $language is validated before it is used to build a file path.
        if (preg_match('/^[A-Za-z]{2,3}([_-][A-Za-z0-9]+)*$/', $language) === 1) {
            $file = $this->resourcesFolder() . 'lang/' . $language . '.php';

            if (is_file($file)) {
                $translations = $this->loadTranslationFile($file);
            }
        }

        // The sorting prefix is added here, once, to the module title (also when the language has no
        // translation of it and the English text is used, and also for languages without a file).
        $title                           = $translations[self::MODULE_TITLE] ?? self::MODULE_TITLE;
        $translations[self::MODULE_TITLE] = self::TITLE_PREFIX . (is_string($title) ? $title : self::MODULE_TITLE);

        return $translations;
    }

    /**
     * Raw content, to be added at the end of the <head> element.
     * Typically, this will be <link> and <meta> elements.
     *
     * @return string
     */
    public function headContent(): string
    {
        $html = "\n" . '<link rel="stylesheet" href="' . e($this->assetUrl('css/style.css')) . '">' . "\n";

        // The @font-face rules are generated here (and not written in style.css) because the font URL
        // has to be the one webtrees builds with assetUrl(), which does not have a fixed form.
        $font_css = $this->symbolFontCss();

        if ($font_css !== '') {
            $html .= '<style>' . "\n" . $font_css . '</style>' . "\n";
        }

        return $html;
    }

    /**
     * CSS that lets every browser (also Android ones, which do not have these glyphs installed) draw the
     * link symbols: @font-face rules for the fonts bundled in resources/fonts/ and the rules that apply them.
     *
     * The bundled font is the LAST option of the font stack, after the fonts of the device, so it is only
     * used (and downloaded) for the characters that the device cannot draw by itself.
     *
     * The result is meant for the content of a <style> element, where HTML entities are NOT decoded, so the
     * only value that is not a constant (the URL) is escaped as a CSS string, not as HTML.
     *
     * @return string Empty if the font files are missing, so a missing file never breaks a page.
     */
    public function symbolFontCss(): string
    {
        $css = '';

        foreach (self::SYMBOL_FONT_FACES as $asset => $unicode_range) {
            if (!is_file($this->resourcesFolder() . $asset)) {
                continue;
            }

            $css .= '@font-face{font-family:"' . self::SYMBOL_FONT_FAMILY . '";font-style:normal;font-weight:400;'
                . 'font-display:swap;src:url("' . $this->cssString($this->assetUrl($asset)) . '") format("woff");'
                . 'unicode-range:' . $unicode_range . ';}' . "\n";
        }

        if ($css === '') {
            return '';
        }

        // Elements (the link in the sidebar, the field and the buttons of the preferences page) that only
        // contain the symbol. The fonts of the device go first so that, where they have a better glyph, it is used.
        $css .= '.mitalteli-link-symbol,.mitalteli-show-xref #link-symbol,.mitalteli-show-xref #new-custom-symbol,'
            . '.mitalteli-show-xref button[data-symbol]{font-family:system-ui,"Segoe UI Symbol","Apple Symbols","'
            . self::SYMBOL_FONT_FAMILY . '",sans-serif}' . "\n"
            // The field to add symbols also has a text placeholder, which keeps the font of the page.
            . '.mitalteli-show-xref #new-custom-symbol::placeholder{font-family:var(--bs-body-font-family,system-ui,sans-serif)}' . "\n";

        return $css;
    }

    /**
     * Escape a value to be put between double quotes in CSS (a string or a url("...")).
     * Backslash and quote would end the string; line breaks are not allowed in it; < and > are escaped
     * so the value can never close the <style> element it is written in.
     *
     * @param string $value
     *
     * @return string
     */
    private function cssString(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '"'  => '\\"',
            "\n" => '\\A ',
            "\r" => '\\D ',
            '<'  => '\\3C ',
            '>'  => '\\3E ',
        ]);
    }

    /**
     * Raw content, to be added at the end of the <body> element.
     * Typically, this will be <script> elements.
     *
     * https://getbootstrap.com/docs/5.2/components/tooltips/#enable-tooltips
     *
     * @return string
     */
    public function bodyContent(): string
    {
        return '';
    }

    /**
     * The text that appears on the sidebar's title.
     *
     * @param Individual|mixed|null $individual Compatible with 2.0 (null), 2.1 and later (Individual)
     * @return string
     */
    public function sidebarTitle($individual = null): string
    {
        // 1. Make sure the object is an instance of Individual (for webtrees 2.1 / 2.2)
        if (!$individual instanceof Individual) {
            $individual = null;
        }

        // 2. If running on webtrees 2.0, $individual will be null.
        if ($individual === null) {
            // Optional: get the current individual from the request if the logic needs it
            // $individual = ...
        }

        return view($this->name() . '::sidebar-header', [
            'module'            => $this,
            'individual'        => $individual,
            'with_uid'          => $this->getPreference('with-uid', '1'),
            'with_css'          => $this->getPreference('with-css', '1'),
            'with_link_symbol'  => $this->getPreference('with-link-symbol', '1'),
            'link_symbol'       => $this->getPreference('link-symbol', '⮺'),
            'is_admin'          => Auth::isAdmin(),
        ]);
    }

    /**
     * The default position for this sidebar.  It can be changed in the control panel.
     *
     * @return int
     */
    public function defaultSidebarOrder(): int
    {
        return (int) $this->getPreference('sidebar-order', '10');
    }

    /**
     * Does this sidebar have anything to display for this individual?
     *
     * @param Individual $individual
     *
     * @return bool
     */
    public function hasSidebarContent(Individual $individual): bool
    {
        return true;
    }

    /**
     * Load this sidebar synchronously.
     *
     * @param Individual $individual
     *
     * @return string
     */
    public function getSidebarContent(Individual $individual): string
    {
        $expand_sidebar     = (bool) $this->getPreference('expand-sidebar') && Auth::isEditor($individual->tree());

        return view($this->name() . '::sidebar', [
            'expand_sidebar'    => $expand_sidebar,
            'individual'        => $individual,
            'tree'              => $individual->tree(),
            'module'            => $this,
            'module_basename'   => $this->name(),
            'with_uid'          => $this->getPreference('with-uid', '1'),
            'with_css'          => $this->getPreference('with-css', '1'),
            'with_link_symbol'  => $this->getPreference('with-link-symbol', '1'),
            'link_symbol'       => $this->getPreference('link-symbol', '⮺'),
        ]);
    }

    /**
     * Show user preference interface.
     *
     * @param ServerRequestInterface|null $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(?ServerRequestInterface $request = null): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $request ??= Registry::container()->get(ServerRequestInterface::class);
        $cookies   = $request->getCookieParams();
        $back_url  = $this->safeBackUrl($cookies['MSHXREF_PAGE'] ?? '', $request->getUri()->getHost());

        $custom_symbols = $this->getPreference('custom-link-symbols', '');
        $custom_symbols_array = $this->validSymbols(explode(',', $custom_symbols));

        return $this->viewResponse($this->name() . '::settings', [
            'expand_sidebar'      => $this->getPreference('expand-sidebar'),
            'with_uid'            => $this->getPreference('with-uid', '1'),
            'with_css'            => $this->getPreference('with-css', '1'),
            'with_link_symbol'    => $this->getPreference('with-link-symbol', '1'),
            'link_symbol'         => $this->getPreference('link-symbol', '⮺'),
            'custom_link_symbols' => $custom_symbols_array,
            // select-number compares with ===, so the value must be an int to preselect the stored option.
            'sidebar_order'       => (int) $this->getPreference('sidebar-order', '10'),
            'title'               => $this->title(),
            'control_panel_url'   => $this->controlPanelUrl(),
            'back_url'            => $back_url,
            // The administration layout does not print the head content of the modules.
            'symbol_font_css'     => $this->symbolFontCss(),
        ]);
    }

    /**
     * Save the user preference.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        if ($params['save'] === '1') {
            $this->setPreference('expand-sidebar', $params['expand-sidebar'] ?? '0');
            $this->setPreference('with-uid', $params['with-uid'] ?? '0');
            $this->setPreference('with-css', $params['with-css'] ?? '0');
            $this->setPreference('with-link-symbol', $params['with-link-symbol'] ?? '0');
            $this->setPreference('link-symbol', $params['link-symbol'] ?? '⮺');
            $this->setPreference('sidebar-order', $params['sidebar-order'] ?? '10');
            $default_symbols = ['⮺','→','↗','⇒','»','›','▶','🡆'];
            $custom_symbols = is_string($params['custom-link-symbols'] ?? null) ? trim($params['custom-link-symbols']) : '';
            $symbols_array = $this->validSymbols(explode(',', $custom_symbols));
            $link_symbol = trim($params['link-symbol'] ?? '⮺');
            if (mb_strlen($link_symbol) > 0 && mb_strlen($link_symbol) <= 2 && !in_array($link_symbol, $symbols_array, true) && !in_array($link_symbol, $default_symbols, true)) {
                $symbols_array[] = $link_symbol;
            }
            if (!empty($params['new-custom-symbol'])) {
                $new_symbol = trim($params['new-custom-symbol']);
                if (mb_strlen($new_symbol) > 0 && mb_strlen($new_symbol) <= 2 && !in_array($new_symbol, $symbols_array, true)) {
                    $symbols_array[] = $new_symbol;
                }
            }
            $symbols_array = array_values(array_unique($symbols_array));
            $this->setPreference('custom-link-symbols', implode(',', $symbols_array));
            $message = I18N::translate('The preferences for the module “%s” have been updated.', $this->title());
            FlashMessages::addMessage($message, 'success');
        }

        return redirect($this->getConfigLink());
    }

    /**
     * Get the first UID for this record
     * If the GedcomRecord has no firstUid method, takes the information directly from the record 
     *
     * @return string
     */
    public function firstUid(GedcomRecord $record): string
    {
        if (ShowXrefModule::firstUidMethodExists()) {
            return $record->firstUid();
        } else {
            $regexTmp = '/\n1 _?UID (' . ShowXrefModule::REGEX_UID_INTERNAL . ')(:?\n|$)/';
            if (preg_match($regexTmp, $record->gedcom(), $matches)) {
                return $matches[1];
            }
        }
        return '';
    }

    /**
     * A function to evaluate if the name of an individual can be shown, taking into account all the privacy settings that webtrees has (tree, individual, fact, individual fact).
     * This is needed because canShowName() only evaluates the tree and individual privacy settings, but not the fact or individual fact privacy settings.
     *
     * @param Individual $individual
     * @return bool
     */
    function reallyCanShowName(?Individual $individual) : bool 
    {
        if ($individual === null) {
            return true;
        }

        $tags = ['NAME'];
        $auth_level = Auth::accessLevel($individual->tree(),Auth::user());
        $debug_string = '';

        $can_show_name = $individual->canShow();

        if ($can_show_name) {
            $can_show_name = $individual->canShowName();
            $debug_string = $debug_string . 'a';
            if (! $can_show_name) {
                $debug_string = $debug_string . 'Z';
            }
        }

        if ($can_show_name) {
            $names = $individual->facts($tags, false, $auth_level);
            $debug_string = $debug_string . 'b';
            if (count($names) > 0) {
                #$debug_string = $debug_string . '[c1' . print_r($names, true).'c1]';
                $debug_string = $debug_string . 'c';
                if ($names[0]->canShow()) {
                    $debug_string = $debug_string . 'dFCS';
                } else {
                    $can_show_name = false;
                    $debug_string = $debug_string . 'e';
                    $debug_string = $debug_string . 'Z';
                    #$debug_string = str_replace($spouse_tmp->fullName(), I18N::translate('Private'), $title);
                }
            } else {
                $debug_string = $debug_string . 'f';
            }
        }

        $xref = $individual->xref();

        if ($can_show_name) {
            // Does this record have a default RESN?
            $individual_privacy = $individual->tree()->getIndividualPrivacy();
            $debug_string = $debug_string . 'g';
            #$debug_string = $debug_string . '[g2' . print_r($individual_privacy, true).'g2]';

            if (isset($individual_privacy[$individual->xref()])) {
                $can_show_name = self::restrictionAllows($individual_privacy[$xref], $auth_level);
                $debug_string = $debug_string . 'h';
            }
            if (! $can_show_name) {
                $debug_string = $debug_string . 'Z';
            }
        }

        if ($can_show_name) {
            $fact_privacy = $individual->tree()->getFactPrivacy();
            $debug_string = $debug_string . 'i';
            #$debug_string = $debug_string . '[i2' . print_r($fact_privacy, true).'i2]';

            foreach ($tags as $tag) {
                if (isset($fact_privacy[$tag])) {
                    $can_show_name = self::restrictionAllows($fact_privacy[$tag], $auth_level);
                    $debug_string = $debug_string . 'j';
                }
                if (! $can_show_name) {
                    $debug_string = $debug_string . 'Z';
                    break;
                }
            }
        }

        if ($can_show_name) {
            $individual_fact_privacy = $individual->tree()->getIndividualFactPrivacy();
            $debug_string = $debug_string . 'k';
            #$debug_string = $debug_string . '[k2' . print_r($individual_fact_privacy, true).'k2]';

            foreach ($tags as $tag) {
                if (isset($individual_fact_privacy[$xref][$tag])) {
                    $can_show_name = self::restrictionAllows($individual_fact_privacy[$xref][$tag], $auth_level);
                    $debug_string = $debug_string . 'l';
                }
                if (! $can_show_name) {
                    $debug_string = $debug_string . 'Z';
                    break;
                }
            }
        }

        #print "<pre>" . print_r($debug_string, true) . "</pre>";
        return $can_show_name;
    }

    /**
     * Keep only valid link symbols: trimmed, 1 or 2 characters long, without duplicates.
     * The same limit is applied to the symbols typed in the form and the link symbol is later
     * output inside HTML and JavaScript, so values coming from the stored list are validated too.
     *
     * @param array<mixed> $symbols
     *
     * @return array<int,string>
     */
    private function validSymbols(array $symbols): array
    {
        $valid = [];

        foreach ($symbols as $symbol) {
            if (!is_string($symbol)) {
                continue;
            }

            $symbol = trim($symbol);

            if (mb_strlen($symbol) >= 1 && mb_strlen($symbol) <= 2) {
                $valid[] = $symbol;
            }
        }

        return array_values(array_unique($valid));
    }

    /**
     * Does a privacy restriction (default_resn table) allow a user with the given access level to see an item?
     *
     * Up to webtrees 2.2 both values are integers (2 = visitors, 1 = members, 0 = managers, -1 = hidden) and the
     * item is visible when restriction >= user level. Since webtrees 2.3 both are AccessLevel enums, which cannot be
     * compared with >= and provide allows() instead. Duck typing is used because AccessLevel does not exist in older
     * versions.
     *
     * @param mixed $restriction int (webtrees <= 2.2) or AccessLevel (webtrees >= 2.3)
     * @param mixed $user_level  int (webtrees <= 2.2) or AccessLevel (webtrees >= 2.3)
     *
     * @return bool
     */
    private static function restrictionAllows($restriction, $user_level): bool
    {
        if (is_object($restriction) && method_exists($restriction, 'allows')) {
            return (bool) $restriction->allows($user_level);
        }

        return (int) $restriction >= (int) $user_level;
    }

    /**
     * Load a translation file: a PHP file that returns an array of "English text" => "translation".
     * It is included directly because Fisharebest\Localization\Translation, which used to read it, was replaced in
     * webtrees 2.3 by Fisharebest\Webtrees\I18N\Translation (and the replacement cannot be used in older versions).
     * The caller must validate the path.
     *
     * @param string $file
     *
     * @return array<string,mixed>
     */
    private function loadTranslationFile(string $file): array
    {
        $translations = include $file;

        return is_array($translations) ? $translations : [];
    }

    /**
     * URL of the control panel page. The request handler was in Http\RequestHandlers up to webtrees 2.2
     * and it is in Http\Controllers since webtrees 2.3.
     *
     * @return string Empty string if the page cannot be found
     */
    private function controlPanelUrl(): string
    {
        $candidates = [
            'Fisharebest\\Webtrees\\Http\\RequestHandlers\\ControlPanel',
            'Fisharebest\\Webtrees\\Http\\Controllers\\ControlPanel',
        ];

        foreach ($candidates as $class) {
            if (class_exists($class)) {
                return route($class);
            }
        }

        return '';
    }

    /**
     * The "back to the last individual page" URL is stored by JavaScript in a cookie, which the browser (or
     * anyone able to set a cookie for this site) controls. Only a plain http(s) URL of the current host is accepted,
     * so it can never be a "javascript:" or "data:" URL, nor a link to another site.
     *
     * @param mixed  $url
     * @param string $host
     *
     * @return string Empty string if the URL is not acceptable
     */
    private function safeBackUrl($url, string $host): string
    {
        if (!is_string($url) || $url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return '';
        }

        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true) || strcasecmp($parts['host'], $host) !== 0) {
            return '';
        }

        return $url;
    }

    /**
     * A breaking change in webtrees 2.2.0 changes how the classes are retrieved.
     * This function allows support for both 2.1.X and 2.2.X versions
     * @param $class
     * @return mixed
     */
    static function getClass($class)
    {
        if (version_compare(Webtrees::VERSION, '2.2.0', '>=')) {
            return Registry::container()->get($class);
        } else {
            return app($class);
        }
    }
};
