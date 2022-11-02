<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Payment\Test\Unit\Helper;

use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\TestFramework\Unit\Matcher\MethodInvokedAtIndex;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Info;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\MethodInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DataTest extends TestCase
{
    /** @var Data */
    private $helper;

    /**  @var MockObject */
    private $scopeConfig;

    /**  @var MockObject */
    private $initialConfig;

    /**  @var MockObject */
    private $methodFactory;

    /**
     * @var MockObject
     */
    private $layoutMock;

    /**
     * @var MockObject
     */
    private $appEmulation;

    protected function setUp(): void
    {
        $objectManagerHelper = new ObjectManager($this);
        $className = Data::class;
        $arguments = $objectManagerHelper->getConstructArguments($className);
        /** @var Context $context */
        $context = $arguments['context'];
        $this->scopeConfig = $context->getScopeConfig();
        $this->layoutMock = $this->getMockForAbstractClass(LayoutInterface::class);
        $layoutFactoryMock = $arguments['layoutFactory'];
        $layoutFactoryMock->expects($this->once())->method('create')->willReturn($this->layoutMock);

        $this->methodFactory = $arguments['paymentMethodFactory'];
        $this->appEmulation = $arguments['appEmulation'];
        $this->initialConfig = $arguments['initialConfig'];

        $this->helper = $objectManagerHelper->getObject($className, $arguments);
    }

    public function testGetMethodInstance()
    {
        list($code, $class, $methodInstance) = ['method_code', 'method_class', 'method_instance'];

        $this->scopeConfig->expects(
            $this->once()
        )->method(
            'getValue'
        )->willReturn(
            $class
        );
        $this->methodFactory->expects(
            $this->any()
        )->method(
            'create'
        )->with(
            $class
        )->willReturn(
            $methodInstance
        );

        $this->assertEquals($methodInstance, $this->helper->getMethodInstance($code));
    }

    public function testGetMethodInstanceWithException()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->scopeConfig->expects($this->once())
            ->method('getValue')
            ->willReturn(null);

        $this->helper->getMethodInstance('code');
    }

    /**
     * @param array $methodA
     * @param array $methodB
     *
     * @dataProvider getSortMethodsDataProvider
     */
    public function testSortMethods(array $methodA, array $methodB)
    {
        $this->initialConfig->expects($this->once())
            ->method('getData')
            ->willReturn(
                [
                    Data::XML_PATH_PAYMENT_METHODS => [
                        $methodA['code'] => $methodA['data'],
                        $methodB['code'] => $methodB['data'],
                        'empty' => [],

                    ]
                ]
            );

        $this->scopeConfig->expects(new MethodInvokedAtIndex(0))
            ->method('getValue')
            ->with(sprintf('%s/%s/model', Data::XML_PATH_PAYMENT_METHODS, $methodA['code']))
            ->willReturn(AbstractMethod::class);
        $this->scopeConfig->expects(new MethodInvokedAtIndex(1))
            ->method('getValue')
            ->with(
                sprintf('%s/%s/model', Data::XML_PATH_PAYMENT_METHODS, $methodB['code'])
            )
            ->willReturn(AbstractMethod::class);
        $this->scopeConfig->expects(new MethodInvokedAtIndex(2))
            ->method('getValue')
            ->with(sprintf('%s/%s/model', Data::XML_PATH_PAYMENT_METHODS, 'empty'))
            ->willReturn(null);

        $methodInstanceMockA = $this->getMockBuilder(MethodInterface::class)
            ->getMockForAbstractClass();
        $methodInstanceMockA->expects($this->any())
            ->method('isAvailable')
            ->willReturn(true);
        $methodInstanceMockA->expects($this->any())
            ->method('getConfigData')
            ->with('sort_order', null)
            ->willReturn($methodA['data']['sort_order']);

        $methodInstanceMockB = $this->getMockBuilder(MethodInterface::class)
            ->getMockForAbstractClass();
        $methodInstanceMockB->expects($this->any())
            ->method('isAvailable')
            ->willReturn(true);
        $methodInstanceMockB->expects($this->any())
            ->method('getConfigData')
            ->with('sort_order', null)
            ->willReturn($methodB['data']['sort_order']);

        $this->methodFactory->expects($this->at(0))
            ->method('create')
            ->willReturn($methodInstanceMockA);

        $this->methodFactory->expects($this->at(1))
            ->method('create')
            ->willReturn($methodInstanceMockB);

        $sortedMethods = $this->helper->getStoreMethods();

        $this->assertGreaterThan(
            array_shift($sortedMethods)->getConfigData('sort_order'),
            array_shift($sortedMethods)->getConfigData('sort_order')
        );
    }

    public function testGetMethodFormBlock()
    {
        list($blockType, $methodCode) = ['method_block_type', 'method_code'];

        $methodMock = $this->getMockBuilder(MethodInterface::class)
            ->getMockForAbstractClass();
        $layoutMock = $this->getMockBuilder(LayoutInterface::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMockForAbstractClass();
        $blockMock = $this->getMockBuilder(BlockInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['setMethod', 'toHtml'])
            ->getMockForAbstractClass();

        $methodMock->expects($this->once())->method('getFormBlockType')->willReturn($blockType);
        $methodMock->expects($this->once())->method('getCode')->willReturn($methodCode);
        $layoutMock->expects($this->once())->method('createBlock')
            ->with($blockType, $methodCode)
            ->willReturn($blockMock);
        $blockMock->expects($this->once())->method('setMethod')->with($methodMock);

        $this->assertSame($blockMock, $this->helper->getMethodFormBlock($methodMock, $layoutMock));
    }

    public function testGetInfoBlock()
    {
        $blockType = 'method_block_type';

        $methodMock = $this->getMockBuilder(MethodInterface::class)
            ->getMockForAbstractClass();
        $infoMock = $this->getMockBuilder(Info::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();
        $blockMock = $this->getMockBuilder(BlockInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['setInfo', 'toHtml'])
            ->getMockForAbstractClass();

        $infoMock->expects($this->once())->method('getMethodInstance')->willReturn($methodMock);
        $methodMock->expects($this->once())->method('getInfoBlockType')->willReturn($blockType);
        $this->layoutMock->expects($this->once())->method('createBlock')
            ->with($blockType)
            ->willReturn($blockMock);
        $blockMock->expects($this->once())->method('setInfo')->with($infoMock);

        $this->assertSame($blockMock, $this->helper->getInfoBlock($infoMock));
    }

    public function testGetInfoBlockHtml()
    {
        list($storeId, $blockHtml, $secureMode, $blockType) = [1, 'HTML MARKUP', true, 'method_block_type'];

        $methodMock = $this->getMockBuilder(MethodInterface::class)
            ->getMockForAbstractClass();
        $infoMock = $this->getMockBuilder(Info::class)
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();
        $paymentBlockMock = $this->getMockBuilder(BlockInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['setArea', 'setIsSecureMode', 'getMethod', 'setStore', 'toHtml', 'setInfo'])
            ->getMockForAbstractClass();

        $this->appEmulation->expects($this->once())
            ->method('startEnvironmentEmulation')
            ->with($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);
        $infoMock->expects($this->once())->method('getMethodInstance')->willReturn($methodMock);
        $methodMock->expects($this->once())->method('getInfoBlockType')->willReturn($blockType);
        $this->layoutMock->expects($this->once())->method('createBlock')
            ->with($blockType)
            ->willReturn($paymentBlockMock);
        $paymentBlockMock->expects($this->once())->method('setInfo')->with($infoMock);
        $paymentBlockMock->expects($this->once())->method('setArea')
            ->with(Area::AREA_FRONTEND)
            ->willReturnSelf();
        $paymentBlockMock->expects($this->once())->method('setIsSecureMode')
            ->with($secureMode);
        $paymentBlockMock->expects($this->once())->method('getMethod')
            ->willReturn($methodMock);
        $methodMock->expects($this->once())->method('setStore')->with($storeId);
        $paymentBlockMock->expects($this->once())->method('toHtml')
            ->willReturn($blockHtml);
        $this->appEmulation->expects($this->once())->method('stopEnvironmentEmulation');

        $this->assertEquals($blockHtml, $this->helper->getInfoBlockHtml($infoMock, $storeId));
    }

    /**
     * @return array
     */
    public function getSortMethodsDataProvider()
    {
        return [
            [
                ['code' => 'methodA', 'data' => ['sort_order' => 0]],
                ['code' => 'methodB', 'data' => ['sort_order' => 1]]
            ],
            [
                ['code' => 'methodA', 'data' => ['sort_order' => 2]],
                ['code' => 'methodB', 'data' => ['sort_order' => 1]],
            ]
        ];
    }
}
