import {routes} from '../routes.ts';
import {getPath, useNavigate} from '@alchemy/navigation';
import {useEffect} from 'react';
import {FullPageLoader} from '@alchemy/phrasea-ui';

type Props = {};

export default function HomePage({}: Props) {
    const navigate = useNavigate();

    useEffect(() => {
        navigate(getPath(routes.assets), {replace: true});
    }, [navigate]);

    return <FullPageLoader />;
}
