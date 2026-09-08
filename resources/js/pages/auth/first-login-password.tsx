import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/first-login-password';

type Props = {
    passwordRules: string;
};

export default function FirstLoginPassword({ passwordRules }: Props) {
    return (
        <>
            <Head title="Defina sua senha" />

            <div className="flex flex-col gap-6">
                <div className="grid gap-2 text-center">
                    <h1 className="text-xl font-semibold tracking-tight">
                        Defina sua nova senha
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Por segurança, escolha uma senha pessoal para continuar
                        usando o Caldas Gestão.
                    </p>
                </div>

                <Form
                    {...update.form()}
                    resetOnError={['password', 'password_confirmation']}
                    className="grid gap-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="password">Nova senha</Label>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoFocus
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                    placeholder="Digite sua nova senha"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirme sua nova senha
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                    placeholder="Digite a senha novamente"
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full"
                            >
                                {processing && <Spinner />}
                                Continuar
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

FirstLoginPassword.layout = {
    title: 'Primeiro acesso',
    description: 'Defina sua senha para continuar',
};
