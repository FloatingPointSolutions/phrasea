import {compileRoutes, getPath} from './src/Router';
import RouterProvider from './src/RouterProvider';
import ModalStack, {StackedModalProps, useModals} from './src/useModalStack';
import {useFormPrompt} from './src/useNavigationPrompt';
import {
    CloseOverlayFunction,
    NavigateToOverlayFunction,
    useCloseOverlay,
    useNavigateToOverlay,
} from './src/useNavigateToOverlay';
import {
    Link,
    Navigate,
    useLocation,
    useNavigate,
    useParams,
    useNavigation,
} from 'react-router-dom';
import OverlayOutlet from './src/Overlay/OverlayOutlet';
import {useOverlay} from './src/Overlay/OverlayContext';

export * from './src/types';
export * from './src/utils';

export {
    useOverlay,
    OverlayOutlet,
    getPath,
    compileRoutes,
    useModals,
    RouterProvider,
    useFormPrompt,
    useNavigateToOverlay,
    useCloseOverlay,
    ModalStack,
    useLocation,
    useParams,
    Link,
    Navigate,
    useNavigate,
    useNavigation,
};
export type {
    StackedModalProps,
    NavigateToOverlayFunction,
    CloseOverlayFunction,
};
