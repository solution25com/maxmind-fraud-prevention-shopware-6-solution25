import template from './maxmind-api-test.html.twig';

const { Component, Mixin } = Shopware;

Component.register('maxmind-api-test', {
    template,

    inject: ['MaxMindApiTestService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    computed: {
        pluginConfig() {
            const parent = this.getConfigParent();

            if (!parent || !parent.actualConfigData) {
                return {};
            }

            const salesChannelId = this.getCurrentSalesChannelId() ?? 'null';

            return parent.actualConfigData[salesChannelId] ?? parent.actualConfigData.null ?? {};
        },
    },

    methods: {
        onProcessFinish() {
            this.isSaveSuccessful = false;
        },

        getConfigParent() {
            let current = this.$parent;

            while (current && typeof current.actualConfigData === 'undefined') {
                current = current.$parent;
            }

            return current ?? null;
        },

        getCurrentSalesChannelId() {
            let current = this.$parent;

            while (current && typeof current.currentSalesChannelId === 'undefined') {
                current = current.$parent;
            }

            return current ? current.currentSalesChannelId : null;
        },

        buildPayload() {
            const config = this.pluginConfig;

            return {
                accountId:
                    config['MaxMind.config.MaxMindConfigAccountId'] ??
                    config.MaxMindConfigAccountId ??
                    null,
                licenseKey:
                    config['MaxMind.config.MaxMindConfigLicenseKey'] ??
                    config.MaxMindConfigLicenseKey ??
                    null,
                salesChannelId: this.getCurrentSalesChannelId(),
            };
        },

        checkConnection() {
            const payload = this.buildPayload();

            if (!payload.accountId || !payload.licenseKey) {
                this.createNotificationError({
                    title: this.$tc('solu1-maxmind-config.apiTest.errorTitle'),
                    message: this.$tc('solu1-maxmind-config.apiTest.missingCredentials'),
                });

                return;
            }

            this.isLoading = true;

            this.MaxMindApiTestService.check(payload)
                .then((response) => {
                    if (response?.success) {
                        this.isSaveSuccessful = true;
                        this.createNotificationSuccess({
                            title: this.$tc('solu1-maxmind-config.apiTest.successTitle'),
                            message: response.message ?? this.$tc('solu1-maxmind-config.apiTest.successMessage'),
                        });

                        return;
                    }

                    this.createNotificationError({
                        title: this.$tc('solu1-maxmind-config.apiTest.errorTitle'),
                        message: response?.message ?? this.$tc('solu1-maxmind-config.apiTest.errorMessage'),
                    });
                })
                .catch((error) => {
                    const message = error?.response?.data?.message ?? error?.message ?? '';

                    this.createNotificationError({
                        title: this.$tc('solu1-maxmind-config.apiTest.errorTitle'),
                        message: message || this.$tc('solu1-maxmind-config.apiTest.errorMessage'),
                    });
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
