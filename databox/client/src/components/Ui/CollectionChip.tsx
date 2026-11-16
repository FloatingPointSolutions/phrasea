import {PropsWithChildren} from 'react';
import {Chip, ChipProps} from '@mui/material';
import {Collection} from '../../types.ts';

type Props = {
    collection?: Collection;
} & PropsWithChildren<ChipProps>;

export const CollectionChip = ({children, collection, ...props}: Props) => {
    return (
        <Chip
            {...props}
            sx={theme => ({
                bgcolor: theme.palette.secondary.main,
                color: theme.palette.secondary.contrastText,
            })}
            {...(collection?.deleted
                ? {
                      style: {textDecoration: 'line-through'},
                  }
                : {})}
            label={children || props.label || collection?.displayName}
        />
    );
};
