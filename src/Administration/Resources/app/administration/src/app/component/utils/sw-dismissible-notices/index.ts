import type { PropType } from 'vue';
import template from './sw-dismissible-notices.html.twig';

const USER_CONFIG_KEY = 'core.dismissedNotices';

/**
 * A change notice that is rendered until the user dismisses it, or until the version
 * in which it becomes obsolete is reached.
 */
interface DismissibleNotice {
    /** Snippet key of the message to render (may contain markup like <b>). */
    key: string;
    /** Version the notice can be removed with, e.g. 'v6.8.0.0'. Once active, the notice is hidden. */
    version: string;
}

/**
 * @private
 * @sw-package framework
 * @description
 * Renders a closable info banner for each given change notice. Dismissing a notice is
 * remembered per user, so a notice only shows again if it was never dismissed. Every
 * notice carries the version it becomes obsolete with — once that version is active the
 * notice is hidden automatically, so announcing a change never requires deprecation
 * handling: add an entry to announce it, delete the entry once its version has shipped.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-dismissible-notices :notices="[{ key: 'my-module.index.someChangeNotice', version: 'v6.8.0.0' }]" />
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['userConfigService'],

    props: {
        notices: {
            type: Array as PropType<DismissibleNotice[]>,
            required: true,
        },

        variant: {
            type: String as PropType<'neutral' | 'info' | 'attention' | 'critical' | 'positive'>,
            required: false,
            default: 'info',
        },
    },

    data(): {
        dismissedNotices: string[];
        isLoaded: boolean;
    } {
        return {
            dismissedNotices: [],
            isLoaded: false,
        };
    },

    computed: {
        visibleNotices(): DismissibleNotice[] {
            if (!this.isLoaded) {
                return [];
            }

            return this.notices.filter(
                (notice) =>
                    !Shopware.Feature.isActive(notice.version) && !this.dismissedNotices.includes(notice.key),
            );
        },
    },

    created() {
        void this.loadDismissedNotices();
    },

    methods: {
        async loadDismissedNotices() {
            const response = await this.userConfigService.search([USER_CONFIG_KEY]);
            const stored = response?.data?.[USER_CONFIG_KEY]?.value;

            this.dismissedNotices = Array.isArray(stored) ? stored : [];
            this.isLoaded = true;
        },

        async onDismiss(notice: string) {
            if (this.dismissedNotices.includes(notice)) {
                return;
            }

            this.dismissedNotices = [
                ...this.dismissedNotices,
                notice,
            ];

            await this.userConfigService.upsert({
                [USER_CONFIG_KEY]: {
                    value: this.dismissedNotices,
                },
            });
        },
    },
});
