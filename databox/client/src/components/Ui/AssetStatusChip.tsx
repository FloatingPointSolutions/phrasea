import {Chip, ChipProps} from '@mui/material';
import {AssetStatus} from '../../types.ts';
import {useTranslation} from 'react-i18next';
import {getAssetStatusTranslations} from '../../translations/assetStatusTranslations.ts';
import {useMemo} from 'react';

type Props = {
    status: AssetStatus;
};

export default function AssetStatusChip({status, ...props}: Props & ChipProps) {
    const {t} = useTranslation();
    const labels = useMemo(() => getAssetStatusTranslations(t), [t]);

    return (
        <Chip
            {...props}
            label={labels[status]}
            sx={theme => ({
                ml: 1,
                bgcolor: theme.palette.secondary.main,
                color: theme.palette.secondary.contrastText,
            })}
        />
    );
}
