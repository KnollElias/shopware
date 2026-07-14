/**
 * @sw-package fundamentals@discovery
 */
import template from './sw-settings-language-list.html.twig';
import './sw-settings-language-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
        'feature',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    shortcuts: {
        OF: 'openFilterSidebar',
    },

    data() {
        return {
            languages: null,
            parentLanguages: null,
            total: 0,
            filterRootLanguages: false,
            filterInheritedLanguages: false,
            isLoading: true,
            sortBy: 'active',
            sortDirection: 'DESC',
            filterSidebarItem: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        listingCriteria() {
            const criteria = new Criteria(this.page, this.limit);
            criteria.addAssociation('locale');
            criteria.addAssociation('translationCode');
            criteria.addAssociation('salesChannels');

            if (this.sortBy) {
                criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection));
            }

            if (this.filterRootLanguages) {
                criteria.addFilter(Criteria.equals('parentId', null));
            }

            if (this.filterInheritedLanguages) {
                criteria.addFilter(Criteria.not('AND', [Criteria.equals('parentId', null)]));
            }

            return criteria;
        },

        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        getColumns() {
            return [
                {
                    property: 'name',
                    label: 'sw-settings-language.list.columnName',
                    dataIndex: 'name',
                    inlineEdit: true,
                },
                {
                    property: 'locale',
                    dataIndex: 'locale.id',
                    label: 'sw-settings-language.list.columnLocaleName',
                },
                {
                    property: 'translationCode.code',
                    label: 'sw-settings-language.list.columnIsoCode',
                },
                {
                    property: 'salesChannels',
                    label: 'sw-settings-language.list.columnSalesChannels',
                    sortable: false,
                },
                {
                    property: 'snippetStatus',
                    label: 'sw-settings-language.list.columnSnippetStatus',
                    sortable: false,
                },
                {
                    property: 'active',
                    dataIndex: 'active',
                    label: 'sw-settings-language.list.columnActive',
                    inlineEdit: 'boolean',
                    align: 'center',
                },
            ];
        },

        allowCreate() {
            return this.acl.can('language.creator');
        },

        allowView() {
            return this.acl.can('language.viewer');
        },

        allowEdit() {
            return this.acl.can('language.editor');
        },

        allowInlineEdit() {
            return this.acl.can('language.editor');
        },

        allowDelete() {
            return this.acl.can('language.deleter');
        },

        cardTitle() {
            return `${this.$t('sw-settings-language.list.cardTitle')} (${this.total})`;
        },

        snippetStatusConfig() {
            return {
                upToDate: {
                    variant: 'positive',
                    statusIndicator: true,
                    label: 'sw-settings-language.list.snippetStatus.upToDate',
                },
                updateAvailable: {
                    variant: 'info',
                    statusIndicator: true,
                    label: 'sw-settings-language.list.snippetStatus.updateAvailable',
                },
                custom: {
                    variant: 'neutral',
                    statusIndicator: false,
                    label: 'sw-settings-language.list.snippetStatus.custom',
                },
            };
        },
    },

    methods: {
        registerFilterSidebarItem(sidebarItem) {
            this.filterSidebarItem = sidebarItem;
        },

        openFilterSidebar() {
            if (!this.filterSidebarItem?.openContent) {
                return;
            }

            this.filterSidebarItem.openContent();
        },

        getList() {
            this.isLoading = true;
            return this.languageRepository.search(this.listingCriteria).then((languageResult) => {
                this.total = languageResult.total || this.total;

                const parentCriteria = new Criteria(1, this.limit);
                const parentIds = {};

                languageResult.forEach((language) => {
                    if (language.parentId) {
                        parentIds[language.parentId] = true;
                    }
                });

                parentCriteria.setIds(Object.keys(parentIds));
                return this.languageRepository.search(parentCriteria).then((parentResult) => {
                    this.languages = languageResult;
                    this.parentLanguages = parentResult;
                    this.isLoading = false;
                });
            });
        },

        salesChannelLabel(item) {
            const count = item.salesChannels?.length ?? 0;

            if (count === 0) {
                return this.$t('sw-settings-language.list.salesChannelNone');
            }

            return this.$t('sw-settings-language.list.salesChannelCount', count);
        },

        /**
         * Placeholder until the snippet-status backend exists. Derives a stable
         * pseudo status from the language id so all badge states are visible.
         */
        getSnippetStatus(item) {
            const statuses = Object.keys(this.snippetStatusConfig);
            const seed = String(item.id ?? item.name ?? '')
                .split('')
                .reduce((sum, char) => sum + char.charCodeAt(0), 0);

            return statuses[seed % statuses.length];
        },

        onUpdateAllSnippets() {
            // Placeholder: snippet update flow pending backend implementation.
        },

        getParentName(item) {
            if (item.parentId === null) {
                return '-';
            }

            return this.parentLanguages.get(item.parentId).name;
        },

        isDefault(languageId) {
            return Shopware.Context.api.systemLanguageId
                ? Shopware.Context.api.systemLanguageId.includes(languageId)
                : false;
        },

        tooltipDelete(languageId) {
            if (!this.acl.can('language.deleter') && !this.isDefault(languageId)) {
                return {
                    message: this.$t('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('language.deleter'),
                    showOnDisabledElements: true,
                };
            }

            return {
                message: '',
                disabled: true,
            };
        },
    },
};
