import en from './en.json';
import fr from './fr.json';
import es from './es.json';
import de from './de.json';
import pt from './pt.json';

import {translations} from '@alchemy/phrasea-framework';

const enMerged = {
    ...en,
    ...translations.en,
};

const frMerged = {
    ...fr,
    ...translations.fr,
};

const esMerged = {
    ...es,
    ...translations.es,
};

const deMerged = {
    ...de,
    ...translations.de,
};

const ptMerged = {
    ...pt,
    ...translations.pt,
};

export {
    enMerged as en,
    frMerged as fr,
    esMerged as es,
    deMerged as de,
    ptMerged as pt,
};
