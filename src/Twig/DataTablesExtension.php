<?php

/*
 * Symfony DataTables Bundle
 * (c) Omines Internetbureau B.V. - https://omines.nl/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Omines\DataTablesBundle\Twig;

use Omines\DataTablesBundle\DataTable;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class DataTablesExtension extends \Twig\Extension\AbstractExtension
{
    /** @var TranslatorInterface */
    protected $translator;

    protected Environment $twig;

    /**
     * DataTablesExtension constructor.
     */
    public function __construct(TranslatorInterface $translator, Environment $twig)
    {
        $this->translator = $translator;
        $this->twig = $twig;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions(): array
    {
        return [
            new \Twig\TwigFunction('datatable_settings', function (DataTable $dataTable) {
                return json_encode([
                    'name' => $dataTable->getName(),
                    'filterHtmlId' => $dataTable->getFilterHtmlId(),
                    'htmlId' => $dataTable->getHtmlId(),
                    'method' => $dataTable->getMethod(),
                    'state' => $dataTable->getPersistState(),
                    'options' => [
                        'language' => $this->getLanguageSettings($dataTable),
                    ],
                ]);
            }, ['is_safe' => ['html', 'js']]),
            new \Twig\TwigFunction('datatable_filter', function (DataTable $dataTable) {
                return $this->twig->render("@DataTables/Filter/form.html.twig", [
                    "datatable" => $dataTable,
                ]);
            }, ['is_safe' => ['html']]),
            new \Twig\TwigFunction('datatable_wrapper', function (DataTable $dataTable) {
                return $this->twig->render("@DataTables/wrapper.html.twig", [
                    "datatable" => $dataTable,
                ]);
            }, ['is_safe' => ['html']]),
        ];
    }

    /**
     * @return array
     */
    private function getLanguageSettings(DataTable $dataTable): array
    {
        /*
        if ($dataTable->isLanguageFromCDN() && null !== ($cdnFile = $this->getCDNLanguageFile())) {
            return ['url' => '//cdn.datatables.net/plug-ins/1.10.15/i18n/' . $cdnFile];
        }
        */

        $domain = $dataTable->getTranslationDomain();

        return [
            'processing' => $this->translator->trans('datatable.datatable.processing', [], $domain),
            'search' => $this->translator->trans('datatable.datatable.search', [], $domain),
            'lengthMenu' => $this->translator->trans('datatable.datatable.lengthMenu', [], $domain),
            'info' => $this->translator->trans('datatable.datatable.info', [], $domain),
            'infoEmpty' => $this->translator->trans('datatable.datatable.infoEmpty', [], $domain),
            'infoFiltered' => $this->translator->trans('datatable.datatable.infoFiltered', [], $domain),
            'infoPostFix' => $this->translator->trans('datatable.datatable.infoPostFix', [], $domain),
            'loadingRecords' => $this->translator->trans('datatable.datatable.loadingRecords', [], $domain),
            'zeroRecords' => $this->translator->trans('datatable.datatable.zeroRecords', [], $domain),
            'emptyTable' => $this->translator->trans('datatable.datatable.emptyTable', [], $domain),
            'searchPlaceholder' => $this->translator->trans('datatable.datatable.searchPlaceholder', [], $domain),
            'paginate' => [
                'first' => $this->translator->trans('datatable.datatable.paginate.first', [], $domain),
                'previous' => $this->translator->trans('datatable.datatable.paginate.previous', [], $domain),
                'next' => $this->translator->trans('datatable.datatable.paginate.next', [], $domain),
                'last' => $this->translator->trans('datatable.datatable.paginate.last', [], $domain),
            ],
            'aria' => [
                'sortAscending' => $this->translator->trans('datatable.datatable.aria.sortAscending', [], $domain),
                'sortDescending' => $this->translator->trans('datatable.datatable.aria.sortDescending', [], $domain),
            ],
        ];
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName(): string
    {
        return 'DataTablesBundle';
    }

    private function getCDNLanguageFile()
    {
        $file = $this->translator->trans('file', [], 'DataTablesCDN');

        // CDN language file does not exists
        if ('file' === $file) {
            return null;
        }

        return $file;
    }
}
