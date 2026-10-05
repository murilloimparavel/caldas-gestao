import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import AdminAuthLayout from '@/layouts/auth/admin-auth-layout';

export default function AuthLayout({
    title = '',
    description = '',
    admin = false,
    children,
}: {
    title?: string;
    description?: string;
    admin?: boolean;
    children: React.ReactNode;
}) {
    const Layout = admin ? AdminAuthLayout : AuthLayoutTemplate;

    return (
        <Layout title={title} description={description}>
            {children}
        </Layout>
    );
}
