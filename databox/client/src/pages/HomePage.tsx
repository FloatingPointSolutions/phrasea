import {routes} from '../routes.ts';
import {getPath, Navigate} from '@alchemy/navigation';

type Props = {};

export default function HomePage({}: Props) {
    return <Navigate to={getPath(routes.assets)} replace />;
}
