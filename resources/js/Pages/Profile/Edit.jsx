import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Mi perfil</h2>}
        >
            <Head title="Mi perfil" />

            <div className="py-4">
                <div className="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
                    {/* Cabecera con avatar */}
                    <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
                        <div className="flex items-center gap-4">
                            <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-gray-900 text-xl font-semibold text-white">
                                {user.name?.charAt(0).toUpperCase() ?? '?'}
                            </div>
                            <div className="min-w-0">
                                <div className="truncate text-lg font-semibold text-gray-900">{user.name}</div>
                                <div className="truncate text-sm text-gray-500">{user.email}</div>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
                        <UpdateProfileInformationForm
                            mustVerifyEmail={mustVerifyEmail}
                            status={status}
                        />
                    </div>

                    <div className="rounded-xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
                        <UpdatePasswordForm />
                    </div>

                    <div className="rounded-xl border border-red-100 bg-white p-6 shadow-sm sm:p-8">
                        <DeleteUserForm />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
