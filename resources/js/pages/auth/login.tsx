import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Entrar" />

            <PasskeyVerify />

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
                            {isRateLimited && canResetPassword && (
                                <div
                                    role="alert"
                                    className="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100"
                                >
                                    <p className="font-medium">
                                        Muitas tentativas de login.
                                    </p>
                                    <p className="mt-1">
                                        Se você não lembra sua senha,
                                        recomendamos redefini-la antes de tentar
                                        novamente.
                                    </p>
                                    <TextLink
                                        href={request()}
                                        className="mt-2 inline-block font-medium underline"
                                    >
                                        Recuperar minha senha
                                    </TextLink>
                                </div>
                            )}

                            <div className="grid gap-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">E-mail</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        required
                                        autoFocus
                                        autoComplete="email"
                                        placeholder="voce@exemplo.com.br"
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center">
                                        <Label htmlFor="password">
                                            Senha
                                        </Label>
                                        {canResetPassword && (
                                            <TextLink
                                                href={request()}
                                                className="ml-auto text-sm"
                                            >
                                                Esqueceu sua senha?
                                            </TextLink>
                                        )}
                                    </div>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="current-password"
                                        placeholder="Digite sua senha"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex items-center space-x-3">
                                    <Checkbox id="remember" name="remember" />
                                    <Label htmlFor="remember">
                                        Manter conectado
                                    </Label>
                                </div>

                                <Button
                                    type="submit"
                                    className="mt-4 w-full"
                                    disabled={processing}
                                    data-test="login-button"
                                >
                                    {processing && <Spinner />}
                                    Entrar
                                </Button>
                            </div>

                            <div className="text-center text-sm text-muted-foreground">
                                Ainda não tem uma conta?{' '}
                                <TextLink href={register()}>Criar conta</TextLink>
                            </div>
                        </>
                    );
                }}
            </Form>

            {status && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Acesse sua conta',
    description: 'Entre com seu e-mail e sua senha para continuar.',
};
