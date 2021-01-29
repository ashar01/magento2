<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types = 1);

namespace Magento\CatalogImportExport\Model\Export;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Observer\SwitchPriceAttributeScopeOnConfigChange;
use Magento\Framework\App\Config\ReinitableConfigInterface;

/**
 * @magentoDataFixtureBeforeTransaction Magento/Catalog/_files/enable_reindex_schedule.php
 * @magentoAppIsolation enabled
 * @magentoDbIsolation enabled
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class ProductTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\CatalogImportExport\Model\Export\Product
     */
    protected $model;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $fileSystem;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * Stock item attributes which must be exported
     *
     * @var array
     */
    public static $stockItemAttributes = [
        'qty',
        'min_qty',
        'use_config_min_qty',
        'is_qty_decimal',
        'backorders',
        'use_config_backorders',
        'min_sale_qty',
        'use_config_min_sale_qty',
        'max_sale_qty',
        'use_config_max_sale_qty',
        'is_in_stock',
        'notify_stock_qty',
        'use_config_notify_stock_qty',
        'manage_stock',
        'use_config_manage_stock',
        'use_config_qty_increments',
        'qty_increments',
        'use_config_enable_qty_inc',
        'enable_qty_increments',
        'is_decimal_divided'
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->fileSystem = $this->objectManager->get(\Magento\Framework\Filesystem::class);
        $this->model = $this->objectManager->create(
            \Magento\CatalogImportExport\Model\Export\Product::class
        );
        $this->productRepository = $this->objectManager->create(ProductRepositoryInterface::class);
    }

    /**
     * @magentoDataFixture Magento/CatalogImportExport/_files/product_export_data.php
     * @magentoDbIsolation enabled
     *
     * @return void
     */
    public function testExport(): void
    {
        $this->model->setWriter(
            $this->objectManager->create(
                \Magento\ImportExport\Model\Export\Adapter\Csv::class
            )
        );
        $exportData = $this->model->export();
        $this->assertContains('New Product', $exportData);

        $this->assertContains('Option 1 & Value 1"', $exportData);
        $this->assertContains('Option 1 & Value 2"', $exportData);
        $this->assertContains('Option 1 & Value 3"', $exportData);
        $this->assertContains('Option 4 ""!@#$%^&*', $exportData);
        $this->assertContains('test_option_code_2', $exportData);
        $this->assertContains('max_characters=10', $exportData);
        $this->assertContains('text_attribute=!@#$%^&*()_+1234567890-=|\\:;""\'<,>.?/', $exportData);
        $occurrencesCount = substr_count($exportData, 'Hello "" &"" Bring the water bottle when you can!');
        $this->assertEquals(1, $occurrencesCount);
    }

    /**
     * @magentoDataFixture Magento/CatalogImportExport/_files/product_export_data_special_chars.php
     * @magentoDbIsolation enabled
     *
     * @return void
     */
    public function testExportSpecialChars(): void
    {
        $this->model->setWriter(
            $this->objectManager->create(
                \Magento\ImportExport\Model\Export\Adapter\Csv::class
            )
        );
        $exportData = $this->model->export();
        $this->assertContains('simple ""1""', $exportData);
        $this->assertContains('Category with slash\/ symbol', $exportData);
    }

    /**
     * @magentoDataFixture Magento/CatalogImportExport/_files/product_export_with_product_links_data.php
     * @magentoDbIsolation enabled
     *
     * @return void
     */
    public function testExportWithProductLinks(): void
    {
        $this->model->setWriter(
            \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
                \Magento\ImportExport\Model\Export\Adapter\Csv::class
            )
        );
        $this->assertNotEmpty($this->model->export());
    }

    /**
     * Verify that all stock item attribute values are exported (aren't equal to empty string)
     *
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @covers \Magento\CatalogImportExport\Model\Export\Product::export
     * @magentoDataFixture Magento/CatalogImportExport/_files/product_export_data.php
     *
     * @return void
     */
    public function testExportStockItemAttributesAreFilled(): void
    {
        $this->markTestSkipped('Test needs to be skipped.');
        $fileWrite = $this->createMock(\Magento\Framework\Filesystem\File\Write::class);
        $directoryMock = $this->createPartialMock(
            \Magento\Framework\Filesystem\Directory\Write::class,
            ['getParentDirectory', 'isWritable', 'isFile', 'readFile', 'openFile']
        );
        $directoryMock->expects($this->any())->method('getParentDirectory')->willReturn('some#path');
        $directoryMock->expects($this->any())->method('isWritable')->willReturn(true);
        $directoryMock->expects($this->any())->method('isFile')->willReturn(true);
        $directoryMock->expects(
            $this->any()
        )->method(
            'readFile'
        )->willReturn(
            'some string read from file'
        );
        $directoryMock->expects($this->once())->method('openFile')->willReturn($fileWrite);

        $filesystemMock = $this->createPartialMock(\Magento\Framework\Filesystem::class, ['getDirectoryWrite']);
        $filesystemMock->expects($this->once())->method('getDirectoryWrite')->willReturn($directoryMock);

        $exportAdapter = new \Magento\ImportExport\Model\Export\Adapter\Csv($filesystemMock);

        $this->model->setWriter($exportAdapter)->export();
    }

    /**
     * Verify header columns (that stock item attributes column headers are present)
     *
     * @param array $headerColumns
     * @return void
     */
    public function verifyHeaderColumns(array $headerColumns): void
    {
        foreach (self::$stockItemAttributes as $stockItemAttribute) {
            $this->assertContains(
                $stockItemAttribute,
                $headerColumns,
                "Stock item attribute {$stockItemAttribute} is absent among header columns"
            );
        }
    }

    /**
     * Verify row data (stock item attribute values)
     *
     * @param array $rowData
     * @return void
     */
    public function verifyRow(array $rowData): void
    {
        foreach (self::$stockItemAttributes as $stockItemAttribute) {
            $this->assertNotSame(
                '',
                $rowData[$stockItemAttribute],
                "Stock item attribute {$stockItemAttribute} value is empty string"
            );
        }
    }

    /**
     * Verifies if exception processing works properly
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/CatalogImportExport/_files/product_export_data.php
     *
     * @return void
     */
    public function testExceptionInGetExportData(): void
    {
        $this->markTestSkipped('Test needs to be skipped.');
        $exception = new \Exception('Error');

        $rowCustomizerMock =
            $this->getMockBuilder(\Magento\CatalogImportExport\Model\Export\RowCustomizerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $loggerMock = $this->getMockBuilder(\Psr\Log\LoggerInterface::class)->getMock();

        $directoryMock = $this->createPartialMock(
            \Magento\Framework\Filesystem\Directory\Write::class,
            ['getParentDirectory', 'isWritable']
        );
        $directoryMock->expects($this->any())->method('getParentDirectory')->willReturn('some#path');
        $directoryMock->expects($this->any())->method('isWritable')->willReturn(true);

        $filesystemMock = $this->createPartialMock(\Magento\Framework\Filesystem::class, ['getDirectoryWrite']);
        $filesystemMock->expects($this->once())->method('getDirectoryWrite')->willReturn($directoryMock);

        $exportAdapter = new \Magento\ImportExport\Model\Export\Adapter\Csv($filesystemMock);

        $rowCustomizerMock->expects($this->once())->method('prepareData')->willThrowException($exception);
        $loggerMock->expects($this->once())->method('critical')->with($exception);

        $collection = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\Catalog\Model\ResourceModel\Product\Collection::class
        );

        /** @var \Magento\CatalogImportExport\Model\Export\Product $model */
        $model = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
            \Magento\CatalogImportExport\Model\Export\Product::class,
            [
                'rowCustomizer' => $rowCustomizerMock,
                'logger' => $loggerMock,
                'collection' => $collection
            ]
        );

        $data = $model->setWriter($exportAdapter)->export();
        $this->assertEmpty($data);
    }

    /**
     * Verify if fields wrapping works correct when "Fields Enclosure" option enabled
     *
     * @magentoDataFixture Magento/CatalogImportExport/_files/product_export_data.php
     *
     * @return void
     */
    public function testExportWithFieldsEnclosure(): void
    {
        $this->model->setParameters(
            [
                \Magento\ImportExport\Model\Export::FIELDS_ENCLOSURE => 1
            ]
        );

        $this->model->setWriter(
            $this->objectManager->create(
                \Magento\ImportExport\Model\Export\Adapter\Csv::class
            )
        );
        $exportData = $this->model->export();

        $this->assertContains('""Option 2""', $exportData);
        $this->assertContains('""Option 3""', $exportData);
        $this->assertContains('""Option 4 """"!@#$%^&*""', $exportData);
        $this->assertContains('text_attribute=""!@#$%^&*()_+1234567890-=|\:;""""\'<,>.?/', $exportData);
    }

    /**
     * Verify that "category ids" filter correctly applies to export result
     *
     * @magentoDataFixture Magento/CatalogImportExport/_files/product_export_with_categories.php
     *
     * @return void
     */
    public function testCategoryIdsFilter(): void
    {
        $this->model->setWriter(
            $this->objectManager->create(
                \Magento\ImportExport\Model\Export\Adapter\Csv::class
            )
        );

        $this->model->setParameters(
            [
                \Magento\ImportExport\Model\Export::FILTER_ELEMENT_GROUP => [
                    'category_ids' => '2,13'
                ]
            ]
        );

        $exportData = $this->model->export();

        $this->assertContains('Simple Product', $exportData);
        $this->assertContains('Simple Product Three', $exportData);
        $this->assertNotContains('Simple Product Two', $exportData);
        $this->assertNotContains('Simple Product Not Visible On Storefront', $exportData);
    }

    /**
     * Verify that export processed successfully with wrong category path
     *
     * @magentoDataFixture Magento/CatalogImportExport/_files/product_export_with_broken_categories_path.php
     *
     * @return void
     */
    public function testExportWithWrongCategoryPath(): void
    {
        $this->model->setWriter(
            $this->objectManager->create(
                \Magento\ImportExport\Model\Export\Adapter\Csv::class
            )
        );

        $this->model->export();
    }

    /**
     * Test 'hide from product page' export for non-default store.
     *
     * @magentoDataFixture Magento/CatalogImportExport/_files/product_export_with_images.php
     *
     * @return void
     */
    public function testExportWithMedia(): void
    {
        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepository */
        $productRepository = $this->objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $product = $productRepository->get('simple', 1);
        $mediaGallery = $product->getData('media_gallery');
        $image = array_shift($mediaGallery['images']);
        $this->model->setWriter(
            $this->objectManager->create(
                \Magento\ImportExport\Model\Export\Adapter\Csv::class
            )
        );
        $exportData = $this->model->export();
        /** @var $varDirectory \Magento\Framework\Filesystem\Directory\WriteInterface */
        $varDirectory = $this->objectManager->get(\Magento\Framework\Filesystem::class)
            ->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
        $varDirectory->writeFile('test_product_with_image.csv', $exportData);
        /** @var \Magento\Framework\File\Csv $csv */
        $csv = $this->objectManager->get(\Magento\Framework\File\Csv::class);
        $data = $csv->getData($varDirectory->getAbsolutePath('test_product_with_image.csv'));
        foreach ($data[0] as $columnNumber => $columnName) {
            if ($columnName === 'hide_from_product_page') {
                self::assertSame($image['file'], $data[2][$columnNumber]);
            }
        }
    }

    /**
     * @magentoDataFixture Magento/CatalogImportExport/_files/product_export_data.php
     *
     * @return void
     */
    public function testExportWithCustomOptions(): void
    {
        $storeCode = 'default';
        $expectedData = [];
        /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepository */
        $productRepository = $this->objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
        $store = $this->objectManager->create(\Magento\Store\Model\Store::class);
        $store->load('default', 'code');
        /** @var \Magento\Catalog\Api\Data\ProductInterface $product */
        $product = $productRepository->get('simple', 1, $store->getStoreId());
        $newCustomOptions = [];
        foreach ($product->getOptions() as $customOption) {
            $defaultOptionTitle = $customOption->getTitle();
            $secondStoreOptionTitle = $customOption->getTitle() . '_' . $storeCode;
            $expectedData['admin_store'][$defaultOptionTitle] = [];
            $expectedData[$storeCode][$secondStoreOptionTitle] = [];
            $customOption->setTitle($secondStoreOptionTitle);
            if ($customOption->getValues()) {
                $newOptionValues = [];
                foreach ($customOption->getValues() as $customOptionValue) {
                    $valueTitle = $customOptionValue->getTitle();
                    $expectedData['admin_store'][$defaultOptionTitle][] = $valueTitle;
                    $expectedData[$storeCode][$secondStoreOptionTitle][] = $valueTitle . '_' . $storeCode;
                    $newOptionValues[] = $customOptionValue->setTitle($valueTitle . '_' . $storeCode);
                }
                $customOption->setValues($newOptionValues);
            }
            $newCustomOptions[] = $customOption;
        }
        $product->setOptions($newCustomOptions);
        $productRepository->save($product);
        $this->model->setWriter(
            $this->objectManager->create(\Magento\ImportExport\Model\Export\Adapter\Csv::class)
        );
        $exportData = $this->model->export();
        /** @var $varDirectory \Magento\Framework\Filesystem\Directory\WriteInterface */
        $varDirectory = $this->objectManager->get(\Magento\Framework\Filesystem::class)
            ->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
        $varDirectory->writeFile('test_product_with_custom_options_and_second_store.csv', $exportData);
        /** @var \Magento\Framework\File\Csv $csv */
        $csv = $this->objectManager->get(\Magento\Framework\File\Csv::class);
        $data = $csv->getData($varDirectory->getAbsolutePath('test_product_with_custom_options_and_second_store.csv'));
        $keys = array_shift($data);
        $products = [];
        foreach ($data as $productData) {
            $products[] = array_combine($keys, $productData);
        }
        $products = array_filter($products, function (array $product) {
            return $product['sku'] === 'simple';
        });
        $customOptionData = [];

        foreach ($products as $product) {
            $storeCode = $product['store_view_code'] ?: 'admin_store';
            $customOptionData[$storeCode] = $this->parseExportedCustomOption($product['custom_options']);
        }

        self::assertSame($expectedData, $customOptionData);
    }

    /**
     * Check that no duplicate entities when multiple custom options used
     *
     * @magentoDataFixture Magento/Catalog/_files/product_simple_with_options.php
     *
     * @return void
     */
    public function testExportWithMultipleOptions(): void
    {
        $expectedCount = 1;
        $resultsFilename = 'export_results.csv';
        $this->model->setWriter(
            $this->objectManager->create(
                \Magento\ImportExport\Model\Export\Adapter\Csv::class
            )
        );
        $exportData = $this->model->export();

        $varDirectory = $this->objectManager->get(\Magento\Framework\Filesystem::class)
            ->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
        $varDirectory->writeFile($resultsFilename, $exportData);
        /** @var \Magento\Framework\File\Csv $csv */
        $csv = $this->objectManager->get(\Magento\Framework\File\Csv::class);
        $data = $csv->getData($varDirectory->getAbsolutePath($resultsFilename));
        $actualCount = count($data) - 1;

        $this->assertSame($expectedCount, $actualCount);
    }

    /**
     * Parse exported custom options
     *
     * @param string $exportedCustomOption
     * @return array
     */
    private function parseExportedCustomOption(string $exportedCustomOption): array
    {
        $customOptions = explode('|', $exportedCustomOption);
        $optionItems = [];
        foreach ($customOptions as $customOption) {
            $parsedOptions = array_values(
                array_map(
                    function ($input) {
                        $data = explode('=', $input);
                        return [$data[0] => $data[1]];
                    },
                    explode(',', $customOption)
                )
            );
            $optionName = array_column($parsedOptions, 'name')[0];
            if (!empty(array_column($parsedOptions, 'option_title'))) {
                $optionItems[$optionName][] = array_column($parsedOptions, 'option_title')[0];
            } else {
                $optionItems[$optionName] = [];
            }
        }

        return $optionItems;
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
     * @magentoConfigFixture current_store catalog/price/scope 1
     * @magentoDbIsolation disabled
     * @magentoAppArea adminhtml
     *
     * @return void
     */
    public function testExportProductWithTwoWebsites(): void
    {
        $globalStoreCode = 'admin';
        $secondStoreCode = 'fixture_second_store';

        $expectedData = [
            $globalStoreCode => 10.0,
            $secondStoreCode => 9.99
        ];

        /** @var \Magento\Store\Model\Store $store */
        $store = $this->objectManager->create(\Magento\Store\Model\Store::class);
        $reinitiableConfig = $this->objectManager->get(ReinitableConfigInterface::class);
        $observer = $this->objectManager->get(\Magento\Framework\Event\Observer::class);
        $switchPriceScope = $this->objectManager->get(SwitchPriceAttributeScopeOnConfigChange::class);
        /** @var \Magento\Catalog\Model\Product\Action $productAction */
        $productAction = $this->objectManager->create(\Magento\Catalog\Model\Product\Action::class);
        /** @var \Magento\Framework\File\Csv $csv */
        $csv = $this->objectManager->get(\Magento\Framework\File\Csv::class);
        /** @var $varDirectory \Magento\Framework\Filesystem\Directory\WriteInterface */
        $varDirectory = $this->objectManager->get(\Magento\Framework\Filesystem::class)
            ->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
        $secondStore = $store->load($secondStoreCode);

        $this->model->setWriter(
            $this->objectManager->create(
                \Magento\ImportExport\Model\Export\Adapter\Csv::class
            )
        );

        $reinitiableConfig->setValue('catalog/price/scope', \Magento\Store\Model\Store::PRICE_SCOPE_WEBSITE);
        $switchPriceScope->execute($observer);

        $product = $this->productRepository->get('simple');
        $productId = $product->getId();
        $productAction->updateWebsites([$productId], [$secondStore->getWebsiteId()], 'add');
        $product->setStoreId($secondStore->getId());
        $product->setPrice('9.99');
        $product->getResource()->save($product);

        $exportData = $this->model->export();

        $varDirectory->writeFile('test_product_with_two_websites.csv', $exportData);
        $data = $csv->getData($varDirectory->getAbsolutePath('test_product_with_two_websites.csv'));

        $columnNumber = array_search('price', $data[0]);
        $this->assertNotFalse($columnNumber);

        $pricesData = [
            $globalStoreCode => (float)$data[1][$columnNumber],
            $secondStoreCode => (float)$data[2][$columnNumber],
        ];

        self::assertSame($expectedData, $pricesData);

        $reinitiableConfig->setValue('catalog/price/scope', \Magento\Store\Model\Store::PRICE_SCOPE_GLOBAL);
        $switchPriceScope->execute($observer);
    }

    /**
     * Verify that "stock status" filter correctly applies to export result
     *
     * @magentoDataFixture Magento/Catalog/_files/multiple_products_with_few_out_of_stock.php
     * @dataProvider filterByQuantityAndStockStatusDataProvider
     *
     * @param string $value
     * @param array $productsIncluded
     * @param array $productsNotIncluded
     * @return void
     */
    public function testFilterByQuantityAndStockStatus(
        string $value,
        array $productsIncluded,
        array $productsNotIncluded
    ): void {
        $exportData = $this->doExport(['quantity_and_stock_status' => $value]);
        foreach ($productsIncluded as $productName) {
            $this->assertContains($productName, $exportData);
        }
        foreach ($productsNotIncluded as $productName) {
            $this->assertNotContains($productName, $exportData);
        }
    }
    /**
     * @return array
     */
    public function filterByQuantityAndStockStatusDataProvider(): array
    {
        return [
            [
                '',
                [
                    'Simple Product OOS',
                    'Simple Product Not Visible',
                    'Simple Product Visible and InStock',
                ],
                [
                ],
            ],
            [
                '1',
                [
                    'Simple Product Not Visible',
                    'Simple Product Visible and InStock',
                ],
                [
                    'Simple Product OOS',
                ],
            ],
            [
                '0',
                [
                    'Simple Product OOS',
                ],
                [
                    'Simple Product Not Visible',
                    'Simple Product Visible and InStock',
                ],
            ],
        ];
    }

    /**
     * Perform export
     *
     * @param array $filters
     * @return string
     */
    private function doExport(array $filters = []): string
    {
        $this->model->setWriter(
            $this->objectManager->create(
                \Magento\ImportExport\Model\Export\Adapter\Csv::class
            )
        );
        $this->model->setParameters(
            [
                \Magento\ImportExport\Model\Export::FILTER_ELEMENT_GROUP => $filters
            ]
        );
        return $this->model->export();
    }
}
