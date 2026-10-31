import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import Notifications from './components/Notifications';
import type { AppProps } from './types';

createInertiaApp<AppProps>({
    pages: { path: './Pages', extension: '.tsx' },
    title: (title) => (title ? title + ' · Posyandu Jeruk' : 'Posyandu Jeruk'),
    progress: { color: '#c2410c' },
    withApp: (app, { page }) => (
        <>
            {app}
            <Notifications initialSuccess={page.props.flash?.success} />
        </>
    ),
});
