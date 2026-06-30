import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const icon = (path) => (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" strokeWidth={1.7} stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" d={path} />
    </svg>
);

const ICONS = {
    dashboard: icon('M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z'),
    accounts: icon('M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z'),
    slaves: icon('M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z'),
    marketplace: icon('M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z'),
    wallet: icon('M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3'),
    logout: icon('M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75'),
};

function SidebarLink({ href, active, children, leftIcon, onClick }) {
    return (
        <Link
            href={href}
            onClick={onClick}
            className={
                'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition ' +
                (active
                    ? 'bg-indigo-50 text-indigo-700'
                    : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900')
            }
        >
            <span className={active ? 'text-indigo-600' : 'text-gray-400 group-hover:text-gray-500'}>
                {leftIcon}
            </span>
            {children}
        </Link>
    );
}

function SidebarContent({ nav, user, onNavigate }) {
    return (
        <div className="flex h-full flex-col">
            {/* Logo */}
            <div className="flex h-16 shrink-0 items-center gap-2 border-b border-gray-100 px-6">
                <Link href={route('dashboard')} className="flex items-center gap-2">
                    <ApplicationLogo className="h-8 w-auto fill-current text-indigo-600" />
                    <span className="text-base font-bold tracking-tight text-gray-800">EAS</span>
                </Link>
            </div>

            {/* Navegación */}
            <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
                {nav.map((item) => (
                    <SidebarLink
                        key={item.name}
                        href={item.href}
                        active={item.active}
                        leftIcon={item.icon}
                        onClick={onNavigate}
                    >
                        {item.name}
                    </SidebarLink>
                ))}
            </nav>

            {/* Usuario */}
            <div className="border-t border-gray-100 p-3">
                <div className="flex items-center gap-3 px-2 py-2">
                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
                        {user.name?.charAt(0).toUpperCase()}
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="truncate text-sm font-medium text-gray-800">{user.name}</div>
                        <div className="truncate text-xs text-gray-400">{user.email}</div>
                    </div>
                </div>
                <div className="mt-1 space-y-1">
                    <SidebarLink
                        href={route('profile.edit')}
                        active={route().current('profile.edit')}
                        leftIcon={icon('M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z')}
                        onClick={onNavigate}
                    >
                        Perfil
                    </SidebarLink>
                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="group flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 transition hover:bg-red-50 hover:text-red-600"
                    >
                        <span className="text-gray-400 group-hover:text-red-500">{ICONS.logout}</span>
                        Cerrar sesión
                    </Link>
                </div>
            </div>
        </div>
    );
}

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const nav = [
        { name: 'Dashboard', href: route('dashboard'), active: route().current('dashboard'), icon: ICONS.dashboard },
        { name: 'Cuentas', href: route('broker-accounts.index'), active: route().current('broker-accounts.*'), icon: ICONS.accounts },
        { name: 'Esclavas', href: route('slave-accounts.index'), active: route().current('slave-accounts.*'), icon: ICONS.slaves },
        { name: 'Marketplace', href: route('marketplace.index'), active: route().current('marketplace.*'), icon: ICONS.marketplace },
        { name: 'Billetera', href: route('wallet.index'), active: route().current('wallet.*'), icon: ICONS.wallet },
    ];

    return (
        <div className="min-h-screen bg-gray-100">
            {/* Sidebar móvil (overlay) */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div
                        className="fixed inset-0 bg-gray-900/50"
                        onClick={() => setSidebarOpen(false)}
                    />
                    <div className="fixed inset-y-0 left-0 w-64 bg-white shadow-xl">
                        <SidebarContent nav={nav} user={user} onNavigate={() => setSidebarOpen(false)} />
                    </div>
                </div>
            )}

            {/* Sidebar fijo (desktop) */}
            <aside className="hidden lg:fixed lg:inset-y-0 lg:left-0 lg:z-30 lg:block lg:w-64 lg:border-r lg:border-gray-200 lg:bg-white">
                <SidebarContent nav={nav} user={user} />
            </aside>

            {/* Contenido */}
            <div className="lg:pl-64">
                {/* Barra superior */}
                <div className="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-gray-200 bg-white px-4 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        onClick={() => setSidebarOpen(true)}
                        className="rounded-md p-2 text-gray-500 transition hover:bg-gray-100 lg:hidden"
                        aria-label="Abrir menú"
                    >
                        <svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" strokeWidth={1.7} stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                        </svg>
                    </button>
                    {header && <div className="flex-1">{header}</div>}
                </div>

                <main className="px-0">{children}</main>
            </div>
        </div>
    );
}
