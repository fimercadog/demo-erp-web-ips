'use client';

import React, { useEffect, useState } from 'react';
import { Calendar, CheckCircle2, AlertCircle, RefreshCw, Unlink, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { api } from '@/lib/api';

export default function GoogleCalendarIntegrationPage() {
  const [loading, setLoading] = useState(true);
  const [connected, setConnected] = useState(false);
  const [integration, setIntegration] = useState<any>(null);
  const [connecting, setConnecting] = useState(false);
  const [disconnecting, setDisconnecting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  const fetchStatus = async () => {
    setLoading(true);
    try {
      const res = await api.get('/integrations/google-calendar/status');
      setConnected(res.data.connected);
      setIntegration(res.data.integration);
    } catch (err) {
      setConnected(false);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchStatus();
  }, []);

  const handleConnect = async () => {
    setConnecting(true);
    setMessage(null);
    try {
      const res = await api.get('/integrations/google-calendar/connect');
      if (res.data.url) {
        window.location.href = res.data.url;
      }
    } catch (err: any) {
      setMessage(err.response?.data?.message || 'Error al iniciar conexión OAuth con Google.');
      setConnecting(false);
    }
  };

  const handleDisconnect = async () => {
    if (!confirm('¿Estás seguro de desvincular la cuenta de Google Calendar?')) return;
    setDisconnecting(true);
    try {
      await api.delete('/integrations/google-calendar/disconnect');
      setConnected(false);
      setIntegration(null);
      setMessage('Cuenta de Google Calendar desvinculada correctamente.');
    } catch (err: any) {
      setMessage('Error al desvincular la cuenta.');
    } finally {
      setDisconnecting(false);
    }
  };

  return (
    <div className="max-w-4xl mx-auto space-y-6 py-6 px-4">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-foreground flex items-center gap-2">
          <Calendar className="h-6 w-6 text-primary" /> Integración con Google Calendar 2-Way Sync
        </h1>
        <p className="text-sm text-muted-foreground mt-1">
          Sincroniza tus citas en tiempo real de forma bidireccional entre FidelOS y Google Calendar. Evita cruces de horario y bloquea automáticamente bloques ocupados.
        </p>
      </div>

      {message && (
        <div className="p-4 rounded-xl border border-primary/30 bg-primary/10 text-primary text-sm flex items-center justify-between">
          <span>{message}</span>
          <Button variant="ghost" size="sm" onClick={() => setMessage(null)}>Cerrar</Button>
        </div>
      )}

      <Card className="border-border/60 bg-card/60 backdrop-blur-md">
        <CardHeader>
          <CardTitle className="text-lg flex items-center justify-between">
            <span>Estado de la Conexión</span>
            {loading ? (
              <span className="text-xs text-muted-foreground">Cargando estado...</span>
            ) : connected ? (
              <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                <CheckCircle2 className="h-3.5 w-3.5" /> Sincronización Activa (2-Way Sync)
              </span>
            ) : (
              <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-600 border border-amber-500/20">
                <AlertCircle className="h-3.5 w-3.5" /> Desconectado
              </span>
            )}
          </CardTitle>
          <p className="text-xs text-muted-foreground">
            Configuración Multi-Tenant cifrada con OAuth2 oficial de Google. Cero exposición de tokens.
          </p>
        </CardHeader>

        <CardContent className="space-y-6">
          {connected && integration ? (
            <div className="space-y-4">
              <div className="p-4 rounded-xl border border-border/80 bg-background/50 grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <span className="text-xs text-muted-foreground block">Cuenta de Google vinculada:</span>
                  <span className="font-semibold text-sm text-foreground">{integration.google_email}</span>
                </div>
                <div>
                  <span className="text-xs text-muted-foreground block">ID de Calendario:</span>
                  <span className="font-mono text-sm text-foreground">{integration.google_calendar_id || 'primary'}</span>
                </div>
                <div>
                  <span className="text-xs text-muted-foreground block">Dirección de Sincronización:</span>
                  <span className="text-sm font-semibold text-emerald-600">Bidireccional (FidelOS ⇄ Google)</span>
                </div>
                <div>
                  <span className="text-xs text-muted-foreground block">Última sincronización:</span>
                  <span className="text-sm text-foreground">
                    {integration.last_synced_at ? new Date(integration.last_synced_at).toLocaleString('es-CO') : 'Reciente'}
                  </span>
                </div>
              </div>

              <div className="flex items-center gap-3 pt-2">
                <Button variant="outline" size="sm" onClick={fetchStatus} disabled={loading} className="gap-2">
                  <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} /> Comprobar Estado
                </Button>
                <Button variant="destructive" size="sm" onClick={handleDisconnect} disabled={disconnecting} className="gap-2">
                  <Unlink className="h-4 w-4" /> Desconectar Google Calendar
                </Button>
              </div>
            </div>
          ) : (
            <div className="text-center py-8 space-y-4">
              <div className="w-16 h-16 rounded-full bg-primary/10 text-primary flex items-center justify-center mx-auto">
                <Calendar className="h-8 w-8" />
              </div>
              <div className="max-w-md mx-auto space-y-1">
                <h3 className="font-bold text-lg text-foreground">Conecta tu cuenta de Google</h3>
                <p className="text-xs text-muted-foreground">
                  Al conectar tu Google Calendar, el motor de citas FidelOS verificará automáticamente tu disponibilidad libre antes de permitir un agendamiento.
                </p>
              </div>

              <div className="pt-4">
                <Button onClick={handleConnect} disabled={connecting} size="default" className="gap-2 font-bold px-8 shadow-md">
                  <ShieldCheck className="h-5 w-5" />
                  {connecting ? 'Redirigiendo a Google...' : 'Conectar con Google Calendar'}
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
