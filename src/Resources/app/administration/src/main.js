import './module/sw-order/page/sw-order-list/index.js';
import './module/sw-order/view/sw-order-detail-general';
import './module/maxmind/extension/sw-order-list';
import './module/maxmind/extension/sw-order-general-info';
import './module/maxmind/styles.css';

import enGB from './module/maxmind/snippet/en-GB.json';
import deDE from './module/maxmind/snippet/de-DE.json';

Shopware.Locale.extend('en-GB', enGB);
Shopware.Locale.extend('de-DE', deDE);

import './component/maxmind-api-test';

import MaxMindApiTestService from './service/maxmind-api-test.service';

Shopware.Service().register('MaxMindApiTestService', () => {
    return new MaxMindApiTestService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService')
    );
});
