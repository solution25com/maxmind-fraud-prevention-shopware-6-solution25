import template from './sw-order-list.html.twig';

const { Component } = Shopware;

Component.override('sw-order-list', {
    template,
    inject: ['repositoryFactory'],
    data() {
        return {
            loadedStates: {},
        };
    },

    watch: {
        orders: {
            handler() {
                this.loadCountryStates();
            },
            immediate: true,
            deep: true,
        },
    },

    computed: {
        orderColumns() {
            const columns = this.$super('orderColumns');

            columns.push({
                property: 'customFields.maxmind_fraud_risk',
                label: 'Fraud Risk Score',
                allowResize: true,
                align: 'right',
                sortable: true,
                rawData: true,
                renderer(value, order) {
                    const fraudScore = order?.customFields?.maxmind_fraud_risk;
                    return fraudScore !== null && fraudScore !== undefined
                        ? parseFloat(fraudScore)
                        : '-';
                },
            });

            return columns;
        },
    },

    methods: {
        async loadCountryStates() {
            const repo = this.repositoryFactory.create('country_state');

            const stateIds = [...new Set(
                (this.orders || [])
                    .map(order => order?.deliveries?.[0]?.shippingOrderAddress?.countryStateId)
                    .filter(Boolean)
            )];

            if (!stateIds.length) return;

            const criteria = new Shopware.Data.Criteria(1, 100);
            criteria.addFilter(Shopware.Data.Criteria.equalsAny('id', stateIds));

            try {
                const result = await repo.search(criteria);
                this.loadedStates = result.reduce((acc, state) => {
                    acc[state.id] = state;
                    return acc;
                }, {});
            } catch (e) {
                console.error('Failed to load country states:', e);
            }
        },

        getStateNameById(id) {
            return this.loadedStates?.[id]?.name ?? '';
        },
    },
});
