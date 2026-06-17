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
use Fisharebest\Localization\Translation;

/**
 * Display media objects as in webtrees 2.0
 */
class ShowXrefModule extends AbstractModule implements ModuleCustomInterface, ModuleSidebarInterface, ModuleGlobalInterface, ModuleConfigInterface
{
    use ModuleCustomTrait;
    use ModuleSidebarTrait;
    use ModuleConfigTrait;

    public const CUSTOM_AUTHOR = 'elysch';
    public const CUSTOM_VERSION = '4.1.0';
    public const GITHUB_REPO = 'webtrees-mitalteli-show-xref';
    public const AUTHOR_WEBSITE = 'https://github.com/elysch/webtrees-mitalteli-show-xref/';
    public const CUSTOM_SUPPORT_URL = self::AUTHOR_WEBSITE . 'issues';

    private const REGEX_UID_INTERNAL = '[0-9a-fA-F]{8}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{12}|[0-9a-fA-F]{36}|[0-9a-fA-F]{38}';

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

        /* I18N: Name of a module */
        return I18N::translate('XREF and UID values module.');
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
        $file = $this->resourcesFolder() . 'lang/' . $language . '.php';

        return file_exists($file) ? (new Translation($file))->asArray() : [];
    }

    /**
     * Raw content, to be added at the end of the <head> element.
     * Typically, this will be <link> and <meta> elements.
     *
     * @return string
     */
    public function headContent(): string
    {
        return "\n" . '<link rel="stylesheet" href="' . e($this->assetUrl('css/style.css')) . '">' . "\n";
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
     * @param Individual|mixed|null $individual Compatible con 2.0 (null), 2.1 (Individual) y 2.2 (Individual)
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
            // Opcional: obtener el individuo actual desde el request si tu lógica lo necesita
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
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        $custom_symbols = $this->getPreference('custom-link-symbols', '');
        $custom_symbols_array = array_filter(array_map('trim', explode(',', $custom_symbols)));

        return $this->viewResponse($this->name() . '::settings', [
            'expand_sidebar'      => $this->getPreference('expand-sidebar'),
            'with_uid'            => $this->getPreference('with-uid', '1'),
            'with_css'            => $this->getPreference('with-css', '1'),
            'with_link_symbol'    => $this->getPreference('with-link-symbol', '1'),
            'link_symbol'         => $this->getPreference('link-symbol', '⮺'),
            'custom_link_symbols' => $custom_symbols_array,
            'sidebar_order'       => $this->getPreference('sidebar-order', '10'),
            'title'               => $this->title(),
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
            if (!empty($params['new-custom-symbol'])) {
                $new_symbol = trim($params['new-custom-symbol']);
                if (mb_strlen($new_symbol) > 0 && mb_strlen($new_symbol) <= 2) {
                    $existing_symbols = $this->getPreference('custom-link-symbols', '');
                    $symbols_array = array_filter(array_map('trim', explode(',', $existing_symbols)));
                    if (!in_array($new_symbol, $symbols_array, true)) {
                        $symbols_array[] = $new_symbol;
                        $this->setPreference('custom-link-symbols', implode(',', $symbols_array));
                    }
                }
            }
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
