using System;
using System.Diagnostics;
using System.IO;
using System.Windows.Forms;

internal static class MinilidiLauncher
{
    [STAThread]
    private static int Main()
    {
        try
        {
            string project = AppDomain.CurrentDomain.BaseDirectory;
            string script = Path.Combine(project, "prototyp", "spoustec.ps1");
            if (!File.Exists(script))
            {
                throw new FileNotFoundException("Minilidi.exe patří do složky hry vedle složky prototyp.");
            }

            string powershell = Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.System),
                @"WindowsPowerShell\v1.0\powershell.exe");
            var start = new ProcessStartInfo
            {
                FileName = powershell,
                Arguments = "-NoProfile -STA -WindowStyle Hidden -ExecutionPolicy Bypass -File \"" + script + "\"",
                WorkingDirectory = project,
                UseShellExecute = false,
                CreateNoWindow = true
            };
            using (Process.Start(start)) { }
            return 0;
        }
        catch (Exception error)
        {
            MessageBox.Show(error.Message, "Minilidi", MessageBoxButtons.OK, MessageBoxIcon.Error);
            return 1;
        }
    }
}
