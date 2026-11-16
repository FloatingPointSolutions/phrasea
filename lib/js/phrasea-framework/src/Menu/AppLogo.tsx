import React from 'react';
import {parseInlineStyle, resolveSx} from '@alchemy/core';
import {Typography} from '@mui/material';
import {AppLogoProps} from './types';

// Without a configured logo the application name is the mark: no image is fetched, so no upstream
// branding can come back through the fallback.
export function AppLogo({appTitle, config, onLogoClick, sx}: AppLogoProps) {
    return (
        <Typography
            variant="h1"
            noWrap
            component="div"
            onClick={onLogoClick}
            sx={theme => ({
                fontSize: 20,
                color: theme.palette.primary.main,
                display: 'flex',
                alignItems: 'center',
                cursor: onLogoClick ? 'pointer' : undefined,
                ...resolveSx(sx, theme),
            })}
        >
            {config.logo?.src ? (
                <img
                    src={config.logo.src}
                    alt={appTitle}
                    style={
                        config.logo.style
                            ? parseInlineStyle(config.logo.style)
                            : {maxHeight: 32, maxWidth: 150}
                    }
                />
            ) : (
                appTitle
            )}
        </Typography>
    );
}
