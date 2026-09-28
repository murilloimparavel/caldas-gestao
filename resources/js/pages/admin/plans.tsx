import PlatformPlans from '@/pages/platform/plans';
import { adminRoutes } from '@/features/admin/types';

export default PlatformPlans;

PlatformPlans.layout = {
    breadcrumbs: [
        { title: 'Administração', href: adminRoutes.dashboard },
        { title: 'Planos', href: adminRoutes.plans },
    ],
};
