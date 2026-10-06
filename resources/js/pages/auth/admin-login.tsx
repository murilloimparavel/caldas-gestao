import { Form, Head, setLayoutProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function AdminLogin({ status, canResetPassword }: Props) {
    setLayoutProps({
        admin: true,
        title: 'Entrar no painel SaaS',
        description: 'Use uma conta de superadministrador para continuar.',
    });

    return (
        <>
            <Head title="Acesso administrativo" />

            <PasskeyVerify
                label="Entrar com passkey administrativa"
                loadingLabel="Validando passkey..."
                separator="Ou entre com e-mail e senha"
            />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => {
                    const isRateLimited = Boolean(
                        errors.email?.toLowerCase().includes('too many'),
                    );

                    return (
                        <>
                            <input
                                type="hidden"
                                name="admin_login_intent"
                                value="1"
                            />

                            {isRateLimited && canResetPassword && (
                                <div
                                    role="alert"
                                    className="rounded-2xl border border-[#755a2e] bg-[#2b2417] p-4 text-sm text-[#f6d899]"
                                >
                                    Muitas tentativas. Aguarde ou redefina sua
                                    senha antes de tentar novamente.
                                    <TextLink
                                        href={request()}
                                        className="mt-2 block font-medium text-[#f1b451] underline"
                                    >
                                        Recuperar minha senha
                                    </TextLink>
                                </div>
                            )}

                            <div className="grid gap-5">
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="email"
                                        className="text-[#c8d9d2]"
                                    >
                                        E-mail de administrador
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        autoComplete="email"
                                        placeholder="admin@empresa.com"
                                        className="h-11 border-[#315159] text-[#edf6f1] placeholder:text-[#66837a] focus-visible:border-[#f1b451] focus-visible:ring-[#f1b451]/30"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between">
                                        <Label
                                            htmlFor="password"
                                            className="text-[#c8d9d2]"
                                        >
                                            Senha
                                        </Label>
                                        {canResetPassword && (
                                            <TextLink
                                                href={request()}
                                                className="text-xs text-[#f1b451]"
                                            >
                                                Esqueci minha senha
                                            </TextLink>
                                        )}
                                    </div>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="current-password"
                                        placeholder="Sua senha"
                                        className="h-11 border-[#315159] text-[#edf6f1] placeholder:text-[#66837a] focus-visible:border-[#f1b451] focus-visible:ring-[#f1b451]/30"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        className="border-[#55756b] data-[state=checked]:border-[#f1b451] data-[state=checked]:bg-[#f1b451] data-[state=checked]:text-[#071417]"
                                    />
                                    <Label
                                        htmlFor="remember"
                                        className="text-sm text-[#a9c0b7]"
                                    >
                                        Manter sessão neste dispositivo
                                    </Label>
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-1 h-11 w-full bg-[#f1b451] text-[#071417] shadow-[0_10px_30px_rgba(241,180,81,0.18)] hover:bg-[#ffca68]"
                                    disabled={processing}
                                    data-test="admin-login-button"
                                >
                                    {processing && <Spinner />}
                                    Acessar painel
                                </Button>
                            </div>

                            <div className="text-center text-sm text-[#89a59b]">
                                Acesso de cliente?{' '}
                                <TextLink
                                    href={login()}
                                    className="text-[#f1b451]"
                                >
                                    Entrar no produto
                                </TextLink>
                            </div>
                        </>
                    );
                }}
            </Form>

            {status && (
                <div className="mt-4 text-center text-sm font-medium text-[#7bd0af]">
                    {status}
                </div>
            )}
        </>
    );
}
