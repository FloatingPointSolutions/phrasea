import {createI18N, createNS} from '@alchemy/i18n';
import * as appLangs from '../translations';
import {getBestFieldTranslatedValue} from '@alchemy/i18n/src/Locale/localeHelper';
import type {Translations} from '@alchemy/i18n';
import {initReactI18next} from 'react-i18next';
import moment from 'moment';
import 'moment/locale/de';
import 'moment/locale/es';
import 'moment/locale/fr';
import 'moment/locale/pt';

const i18n = createI18N({
    initReactI18next,
    resources: createNS(appLangs),
    onLanguageChanged: lng => {
        if (lng) {
            moment.locale(lng);
        }
    },
});

if (i18n.language) {
    moment.locale(i18n.language);
}

export const appLocales = Object.keys(appLangs);

export default i18n;

export function getTranslatedTitle({
    title,
    translations,
}: {
    title: string;
    translations?: Translations;
}): string {
    return getBestFieldTranslatedValue(translations, 'title', title);
}

export function getTranslatedDescription({
    description,
    translations,
}: {
    description?: string | undefined;
    translations?: Translations;
}): string | undefined {
    return getBestFieldTranslatedValue(
        translations,
        'description',
        description
    );
}
